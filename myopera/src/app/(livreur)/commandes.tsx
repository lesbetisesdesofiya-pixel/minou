import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, Pressable, RefreshControl, Linking } from 'react-native';
import { api, fmt, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui, C } from '../../lib/ui';
import { TopBar, StatusBadge } from '../../components/chrome';

export default function LivreurCommandes() {
  const { token, driverId, handleUnauthorized } = useAuth();
  const [tab, setTab] = useState<'new' | 'done'>('new');
  const [orders, setOrders] = useState<any[]>([]);
  const [ready, setReady] = useState<any[]>([]);
  const [err, setErr] = useState('');
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [list, readyRes] = await Promise.all([
        api('/orders?limit=100', { token }),
        api('/delivery/ready', { token }).catch(() => []),
      ]);
      setOrders(Array.isArray(list) ? list : []);
      setReady(Array.isArray(readyRes) ? readyRes : []);
      setErr('');
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Chargement impossible.');
    } finally {
      setRefreshing(false);
    }
  }, [token, handleUnauthorized]);

  useEffect(() => {
    const t = setTimeout(() => {
      load();
    }, 0);
    return () => clearTimeout(t);
  }, [load]);

  useEffect(() => {
    const t = setInterval(() => {
      load();
    }, 20000);
    return () => clearInterval(t);
  }, [load]);

  const mine = orders.filter((o) => driverId && Number(o.assigned_driver_id) === Number(driverId));
  const todo = mine.filter((o) => ['PREPARING', 'READY_FOR_PICKUP'].includes(o.status));
  const done = mine.filter((o) => o.status === 'delivered');
  const cashToday = done.reduce((s, o) => s + Number(o.driver_amount || 0), 0);
  const freeReady = ready.filter((o) => !o.assigned_driver_id);

  const accept = async (orderId: number) => {
    if (!token || !driverId) return;
    try {
      await api('/delivery/assign', { method: 'POST', token, body: { order_id: orderId, driver_id: driverId } });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Action impossible.');
    }
  };

  const complete = async (id: number) => {
    if (!token) return;
    try {
      await api(`/delivery/${id}/complete`, { method: 'PATCH', token });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Action impossible.');
    }
  };

  return (
    <View style={ui.page}>
      <TopBar title="Mes courses" subtitle={driverId ? `Coursier #${driverId} • En service` : 'En service'} live />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        {!!err && <Text style={ui.err}>{err}</Text>}
        {!driverId && <Text style={ui.err}>Compte livreur non lié. Contacte la régie.</Text>}
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={ui.stat}><Text style={ui.statNum}>{done.length}</Text><Text style={ui.statLbl}>Livrées</Text></View>
          <View style={ui.stat}><Text style={ui.statNum}>{fmt(cashToday)}</Text><Text style={ui.statLbl}>Gains</Text></View>
        </View>
        <View style={{ flexDirection: 'row' }}>
          {(['new', 'done'] as const).map((k) => (
            <Pressable key={k} style={[ui.chip, tab === k && ui.chipActive]} onPress={() => setTab(k)}>
              <Text style={[ui.chipText, tab === k && ui.chipTextActive]}>
                {k === 'new' ? `Nouveau (${todo.length + freeReady.length})` : `Livré (${done.length})`}
              </Text>
            </Pressable>
          ))}
        </View>

        {tab === 'new' && (
          <>
            {todo.map((o) => (
              <View key={o.id} style={[ui.card, { borderLeftWidth: 4, borderLeftColor: C.orange }]}>
                <View style={ui.row}>
                  <Text style={{ fontWeight: '800', color: C.navy }}>#OP-{o.id} — En route</Text>
                  <StatusBadge status={o.status} />
                </View>
                <Text style={[ui.subtitle, { marginVertical: 6 }]}>
                  {o.neighborhood} • {o.client_name} • {o.client_phone}
                </Text>
                <Text style={ui.subtitle}>À encaisser : {fmt(o.driver_amount)}</Text>
                <View style={[ui.row, { marginTop: 8 }]}>
                  {!!o.client_phone && (
                    <Pressable style={[ui.btnGhost, ui.btnSm, { flex: 1 }]} onPress={() => Linking.openURL(`tel:${o.client_phone}`)}>
                      <Text style={ui.btnTextDark}>Appeler</Text>
                    </Pressable>
                  )}
                  <Pressable style={[ui.btnOrange, ui.btnSm, { flex: 1 }]} onPress={() => complete(o.id)}>
                    <Text style={ui.btnText}>Confirmer livraison</Text>
                  </Pressable>
                </View>
              </View>
            ))}
            <Text style={{ fontSize: 13, fontWeight: '800', color: C.navy }}>Disponibles au retrait ({freeReady.length})</Text>
            {freeReady.map((o) => (
              <View style={ui.card} key={o.id}>
                <View style={ui.row}>
                  <Text style={{ fontWeight: '800', color: C.navy }}>#OP-{o.id} — Prête</Text>
                  <Text style={[ui.badgeTextAmber, ui.badgeAmber]}>+{fmt(o.driver_amount)}</Text>
                </View>
                <Text style={ui.subtitle}>{o.neighborhood} • {o.client_name}</Text>
                <View style={[ui.row, { marginTop: 8 }]}>
                  <Pressable style={[ui.btnGhost, ui.btnSm, { flex: 1 }]} onPress={load}>
                    <Text style={ui.btnTextDark}>Refuser</Text>
                  </Pressable>
                  <Pressable style={[ui.btnOrange, ui.btnSm, { flex: 1 }]} onPress={() => accept(o.id)}>
                    <Text style={ui.btnText}>Accepter la course</Text>
                  </Pressable>
                </View>
              </View>
            ))}
            {todo.length === 0 && freeReady.length === 0 && (
              <View style={ui.card}><Text style={ui.subtitle}>Aucune course pour le moment. Reste en ligne.</Text></View>
            )}
          </>
        )}

        {tab === 'done' && (
          done.length === 0 ? (
            <View style={ui.card}><Text style={ui.subtitle}>Aucune livraison terminée.</Text></View>
          ) : (
            done.map((o) => (
              <View style={ui.card} key={o.id}>
                <View style={ui.row}>
                  <Text style={{ fontWeight: '800', color: C.navy }}>#OP-{o.id}</Text>
                  <StatusBadge status={o.status} />
                </View>
                <Text style={ui.subtitle}>{o.neighborhood} • {fmt(o.driver_amount)} • {o.driver_paid ? 'Payé' : 'Non reversé'}</Text>
              </View>
            ))
          )
        )}
      </ScrollView>
    </View>
  );
}
