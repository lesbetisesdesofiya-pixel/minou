import { useCallback, useEffect, useRef, useState } from 'react';
import { View, Text, ScrollView, Pressable, Modal, RefreshControl } from 'react-native';
import { BellRing, Bike, Search } from 'lucide-react-native';
import { router } from 'expo-router';
import { api, fmt, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui, C } from '../../lib/ui';
import { TopBar, StatusBadge } from '../../components/chrome';

const TABS = [
  { key: 'new', label: 'Nouvelles', statuses: ['pending', 'En attente de paiement', 'Payée'] },
  { key: 'kitchen', label: 'En cuisine', statuses: ['PREPARING'] },
  { key: 'delivery', label: 'En livraison', statuses: ['READY_FOR_PICKUP'] },
  { key: 'done', label: 'Terminées', statuses: ['delivered'] },
] as const;

export default function Commandes() {
  const { token, handleUnauthorized } = useAuth();
  const [tab, setTab] = useState<string>('new');
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [err, setErr] = useState('');
  const [alertOrder, setAlertOrder] = useState<any | null>(null);
  const seenRef = useRef<Set<number>>(new Set());
  const firstRef = useRef(true);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const list = await api('/orders?limit=100', { token });
      const arr = Array.isArray(list) ? list : [];
      if (!firstRef.current) {
        const fresh = arr.find((o: any) => o.status === 'pending' && !seenRef.current.has(o.id));
        if (fresh) setAlertOrder(fresh);
      }
      firstRef.current = false;
      arr.forEach((o: any) => seenRef.current.add(o.id));
      setOrders(arr);
      setErr('');
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Chargement impossible.');
    } finally {
      setLoading(false);
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
    }, 15000);
    return () => clearInterval(t);
  }, [load]);

  const setStatus = async (id: number, status: string) => {
    if (!token) return;
    try {
      try {
        await api(`/orders/${id}/status`, { method: 'PATCH', token, body: { status } });
      } catch (e) {
        if (status === 'Annulée') await api(`/orders/${id}/status`, { method: 'PATCH', token, body: { status: 'pending' } });
        else throw e;
      }
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Action impossible.');
    } finally {
      setAlertOrder(null);
      load();
    }
  };

  const counts: Record<string, number> = {};
  TABS.forEach((t) => {
    counts[t.key] = orders.filter((o) => (t.statuses as readonly string[]).includes(o.status)).length;
  });
  const active = TABS.find((t) => t.key === tab)!;
  const filtered = orders.filter((o) => (active.statuses as readonly string[]).includes(o.status));

  return (
    <View style={ui.page}>
      <TopBar title="Gestion des commandes" subtitle="Service en direct" live />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        <View style={ui.hero}>
          <Text style={ui.heroBig}>{counts.new} nouvelles</Text>
          <Text style={ui.heroSub}>{orders.length} commandes • auto-refresh 15 s</Text>
        </View>
        {!!err && <Text style={ui.err}>{err}</Text>}
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {TABS.map((t) => (
            <Pressable key={t.key} style={[ui.chip, tab === t.key && ui.chipActive]} onPress={() => setTab(t.key)}>
              <Text style={[ui.chipText, tab === t.key && ui.chipTextActive]}>{t.label} {counts[t.key]}</Text>
            </Pressable>
          ))}
        </ScrollView>
        {loading ? (
          <Text style={ui.subtitle}>Chargement…</Text>
        ) : filtered.length === 0 ? (
          <View style={ui.card}><Text style={ui.subtitle}>Aucune commande dans cet onglet.</Text></View>
        ) : (
          filtered.map((o) => (
            <View style={ui.card} key={o.id}>
              <View style={ui.row}>
                <Text style={{ fontWeight: '800', color: C.navy }}>#OP-{o.id}</Text>
                <StatusBadge status={o.status} />
              </View>
              <Pressable onPress={() => router.push({ pathname: '/(admin)/commande/[id]', params: { id: String(o.id) } })}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 4 }}>
                  <Search color={C.orange} size={14} />
                  <Text style={ui.link}>Détails complets</Text>
                </View>
              </Pressable>
              <Text style={[ui.subtitle, { marginVertical: 6 }]}>
                {o.client_name} • {o.client_phone}{'\n'}{o.neighborhood || o.service_type} • {fmt(o.total_amount)}
              </Text>
              {!!o.driver_first && (
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                  <Bike color={C.muted} size={14} />
                  <Text style={ui.subtitle}>{o.driver_first} {o.driver_last}</Text>
                </View>
              )}
              <View style={[ui.row, { marginTop: 10 }]}>
                {(o.status === 'pending' || o.status === 'En attente de paiement' || o.status === 'Payée') && (
                  <>
                    <Pressable style={[ui.btnOrange, ui.btnSm, { flex: 1 }]} onPress={() => setStatus(o.id, 'PREPARING')}>
                      <Text style={ui.btnText}>Accepter</Text>
                    </Pressable>
                    <Pressable style={[ui.btnGhost, ui.btnSm, { flex: 1 }]} onPress={() => setStatus(o.id, 'Annulée')}>
                      <Text style={ui.btnTextDark}>Refuser</Text>
                    </Pressable>
                  </>
                )}
                {o.status === 'PREPARING' && (
                  <Pressable style={[ui.btnNavy, ui.btnSm, { flex: 1 }]} onPress={() => setStatus(o.id, 'READY_FOR_PICKUP')}>
                    <Text style={ui.btnText}>Prête → livraison</Text>
                  </Pressable>
                )}
                {o.status === 'READY_FOR_PICKUP' && (
                  <Pressable style={[ui.btnNavy, ui.btnSm, { flex: 1 }]} onPress={() => setStatus(o.id, 'delivered')}>
                    <Text style={ui.btnText}>Marquer livrée</Text>
                  </Pressable>
                )}
              </View>
            </View>
          ))
        )}
      </ScrollView>

      <Modal visible={!!alertOrder} transparent animationType="slide" onRequestClose={() => setAlertOrder(null)}>
        <View style={{ flex: 1, backgroundColor: 'rgba(2,6,23,0.7)', justifyContent: 'center', padding: 20 }}>
          <View style={{ backgroundColor: '#fff', borderRadius: 24, overflow: 'hidden' }}>
            <View style={{ backgroundColor: C.orange, padding: 20, alignItems: 'center', gap: 4 }}>
              <BellRing color="#fff" size={34} />
              <Text style={{ color: '#fff', fontWeight: '800', fontSize: 20 }}>Nouvelle commande !</Text>
              <Text style={{ color: '#fff', fontWeight: '700' }}>#OP-{alertOrder?.id} • {fmt(alertOrder?.total_amount)}</Text>
            </View>
            <View style={{ padding: 16, gap: 10 }}>
              <Text style={{ fontWeight: '800', color: C.navy }}>{alertOrder?.client_name}</Text>
              <Text style={ui.subtitle}>{alertOrder?.client_phone} • {alertOrder?.neighborhood}</Text>
              <Pressable style={ui.btnOrange} onPress={() => alertOrder && setStatus(alertOrder.id, 'PREPARING')}>
                <Text style={ui.btnText}>Accepter la commande</Text>
              </Pressable>
              <Pressable style={ui.btnGhost} onPress={() => setAlertOrder(null)}>
                <Text style={ui.btnTextDark}>Plus tard</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}
