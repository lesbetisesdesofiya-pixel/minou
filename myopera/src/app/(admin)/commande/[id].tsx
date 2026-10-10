import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, Pressable, RefreshControl } from 'react-native';
import { router, useLocalSearchParams, Stack } from 'expo-router';
import { api, fmt, UnauthorizedError } from '../../../lib/api';
import { useAuth } from '../../../lib/auth';
import { ui, C } from '../../../lib/ui';
import { TopBar, StatusBadge } from '../../../components/chrome';

export default function CommandeDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token, handleUnauthorized } = useAuth();
  const [order, setOrder] = useState<any | null>(null);
  const [drivers, setDrivers] = useState<any[]>([]);
  const [driverSel, setDriverSel] = useState('');
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [showDrivers, setShowDrivers] = useState(false);

  const load = useCallback(async () => {
    if (!token || !id) return;
    try {
      const [o, persons] = await Promise.all([
        api(`/orders/${id}`, { token }),
        api('/delivery/persons', { token }).catch(() => []),
      ]);
      setOrder(o);
      setDrivers(Array.isArray(persons) ? persons : []);
      setDriverSel(o.assigned_driver_id ? String(o.assigned_driver_id) : '');
      setErr('');
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Commande introuvable.');
    } finally {
      setRefreshing(false);
    }
  }, [token, id, handleUnauthorized]);

  useEffect(() => {
    const t = setTimeout(() => {
      load();
    }, 0);
    return () => clearTimeout(t);
  }, [load]);

  const act = async (fn: () => Promise<void>, okMsg: string) => {
    if (!token) return;
    setMsg('');
    try {
      await fn();
      setMsg(okMsg);
      await load();
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Action impossible.');
    }
  };

  const setStatus = (status: string) =>
    act(async () => {
      try {
        await api(`/orders/${id}/status`, { method: 'PATCH', token, body: { status } });
      } catch (e) {
        if (status === 'Annulée') await api(`/orders/${id}/status`, { method: 'PATCH', token, body: { status: 'pending' } });
        else throw e;
      }
    }, `Commande #${id} mise à jour.`);

  if (!order) {
    return (
      <View style={ui.page}>
        <Stack.Screen options={{ title: `Commande #${id}` }} />
        <TopBar title={`Commande #${id}`} />
        <View style={{ padding: 16 }}>{!!err ? <Text style={ui.err}>{err}</Text> : <Text style={ui.subtitle}>Chargement…</Text>}</View>
      </View>
    );
  }

  const items: any[] = order.items || [];
  const total = order.total ?? order.total_amount ?? 0;
  const activeDrivers = drivers.filter((d) => d.active && !d.suspended);

  return (
    <View style={ui.page}>
      <Stack.Screen options={{ title: `#OP-${order.id}` }} />
      <TopBar title={`Commande #OP-${order.id}`} right={<StatusBadge status={order.status} />} />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        {!!err && <Text style={ui.err}>{err}</Text>}
        {!!msg && <Text style={ui.ok}>{msg}</Text>}
        <Pressable onPress={() => router.back()}>
          <Text style={ui.link}>← Toutes les commandes</Text>
        </Pressable>

        <View style={ui.hero}>
          <Text style={ui.heroBig}>{fmt(total)}</Text>
          <Text style={ui.heroSub}>
            {order.service_type} • Sous-total {fmt(order.subtotal ?? order.subtotal_amount)} • Service {fmt(order.service_fee)} • Livraison {fmt(order.delivery_fee)}
          </Text>
        </View>

        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: C.navy }}>👤 Client</Text>
          <Text style={[ui.subtitle, { marginTop: 6 }]}>
            {order.client_name} • {order.client_phone}{'\n'}📍 {order.neighborhood || '—'}
            {order.table_number ? ` • Table ${order.table_number}` : ''}
          </Text>
          {(order.driver_first || order.driver) && (
            <Text style={ui.subtitle}>🛵 {order.driver_first || order.driver?.first_name} {order.driver_last || order.driver?.last_name || ''}</Text>
          )}
        </View>

        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: C.navy }}>🧾 Articles ({items.length})</Text>
          {items.map((it, i) => (
            <View key={i} style={[ui.row, { paddingVertical: 6 }]}>
              <View style={{ flex: 1 }}>
                <Text style={{ color: C.navy, fontWeight: '700' }}>{it.product_name || it.name} ×{it.quantity}</Text>
                {!!it.options && <Text style={ui.subtitle}>{it.options}</Text>}
              </View>
              <Text style={{ color: C.navy, fontWeight: '800' }}>{fmt(it.price)}</Text>
            </View>
          ))}
          {!!order.notes && <Text style={[ui.subtitle, { marginTop: 8 }]}>📝 Note : {order.notes}</Text>}
        </View>

        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: C.navy }}>⚡ Actions</Text>
          <View style={[ui.row, { marginTop: 8 }]}>
            <Pressable style={[ui.btnOrange, ui.btnSm, { flex: 1 }]} onPress={() => setStatus('PREPARING')}>
              <Text style={ui.btnText}>Accepter</Text>
            </Pressable>
            <Pressable style={[ui.btnGhost, ui.btnSm, { flex: 1 }]} onPress={() => setStatus('Annulée')}>
              <Text style={ui.btnTextDark}>Refuser</Text>
            </Pressable>
          </View>
          <View style={[ui.row, { marginTop: 8 }]}>
            <Pressable style={[ui.btnNavy, ui.btnSm, { flex: 1 }]} onPress={() => setStatus('READY_FOR_PICKUP')}>
              <Text style={ui.btnText}>Prête</Text>
            </Pressable>
            <Pressable style={[ui.btnNavy, ui.btnSm, { flex: 1 }]} onPress={() => setStatus('delivered')}>
              <Text style={ui.btnText}>Livrée</Text>
            </Pressable>
          </View>
        </View>

        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: C.navy }}>🛵 Livreur</Text>
          <Pressable style={[ui.btnGhost, { marginTop: 8 }]} onPress={() => setShowDrivers(!showDrivers)}>
            <Text style={ui.btnTextDark}>
              {driverSel ? `Assigné : ${activeDrivers.find((d) => String(d.id) === driverSel)?.first_name || driverSel} (changer)` : '— Assigner un livreur —'}
            </Text>
          </Pressable>
          {showDrivers &&
            activeDrivers.map((d) => (
              <Pressable
                key={d.id}
                style={{ paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' }}
                onPress={() => {
                  setDriverSel(String(d.id));
                  setShowDrivers(false);
                  act(
                    () => api('/delivery/assign', { method: 'POST', token, body: { order_id: Number(id), driver_id: d.id } }).then(() => {}),
                    'Livreur assigné.'
                  );
                }}
              >
                <Text style={{ color: C.navy }}>{d.first_name} {d.last_name}</Text>
              </Pressable>
            ))}
          <View style={[ui.row, { marginTop: 8 }]}>
            <Text style={ui.subtitle}>
              Gain : {fmt(order.driver_amount)} • {order.driver_paid ? '✅ Reversé' : '⏳ Non reversé'}
            </Text>
          </View>
          <Pressable
            style={[ui.btnGhost, ui.btnSm, { marginTop: 6 }]}
            onPress={() => act(() => api(`/orders/${id}/driver-paid`, { method: 'POST', token, body: { driver_paid: !order.driver_paid } }).then(() => {}), 'Paie livreur mise à jour.')}
          >
            <Text style={ui.btnTextDark}>{order.driver_paid ? 'Marquer non reversé' : 'Marquer reversé'}</Text>
          </Pressable>
        </View>
      </ScrollView>
    </View>
  );
}
