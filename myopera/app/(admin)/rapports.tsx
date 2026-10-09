import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, Pressable, RefreshControl } from 'react-native';
import { api, fmt, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui } from '../../lib/ui';
import { TopBar } from '../../components/chrome';

const PERIODS = [
  ['daily', "Aujourd'hui"],
  ['weekly', '7 jours'],
  ['monthly', 'Ce mois'],
] as const;

export default function Rapports() {
  const { token, handleUnauthorized } = useAuth();
  const [period, setPeriod] = useState<string>('daily');
  const [stats, setStats] = useState<any | null>(null);
  const [rev, setRev] = useState<any | null>(null);
  const [metrics, setMetrics] = useState<any | null>(null);
  const [err, setErr] = useState('');
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [s, r, m] = await Promise.all([
        api('/admin/stats', { token }),
        api(`/analytics/revenue?period=${period}`, { token }),
        api('/analytics/deliveries/metrics', { token }).catch(() => null),
      ]);
      setStats(s);
      setRev(r);
      setMetrics(m);
      setErr('');
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Chargement impossible.');
    } finally {
      setRefreshing(false);
    }
  }, [token, period, handleUnauthorized]);

  useEffect(() => {
    load();
  }, [load]);

  const revRows: any[] = [...(rev?.data || [])].reverse().slice(0, 12);
  const maxRev = Math.max(1, ...revRows.map((d) => Number(d.total_revenue || 0)));

  return (
    <View style={ui.page}>
      <TopBar title="Rapports financiers" subtitle="Activité & clôtures" />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        {!!err && <Text style={ui.err}>{err}</Text>}
        <View style={{ flexDirection: 'row' }}>
          {PERIODS.map(([k, l]) => (
            <Pressable key={k} style={[ui.chip, period === k && ui.chipActive]} onPress={() => setPeriod(k)}>
              <Text style={[ui.chipText, period === k && ui.chipTextActive]}>{l}</Text>
            </Pressable>
          ))}
        </View>

        {!!stats && (
          <>
            <View style={ui.hero}>
              <Text style={ui.heroSub}>{'Chiffre d\u2019affaires total'}</Text>
              <Text style={ui.heroBig}>{fmt(stats.total_revenue)}</Text>
              <View style={{ flexDirection: 'row', gap: 8, marginTop: 8 }}>
                <View style={ui.badgeGreen}><Text style={ui.badgeTextGreen}>{stats.delivered_orders} livrées</Text></View>
                <View style={ui.badgeAmber}><Text style={ui.badgeTextAmber}>{(stats.pending_orders || 0) + (stats.awaiting_payment || 0)} en attente</Text></View>
              </View>
            </View>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={ui.stat}><Text style={ui.statNum}>{stats.total_orders}</Text><Text style={ui.statLbl}>Commandes</Text></View>
              <View style={ui.stat}><Text style={ui.statNum}>{fmt(stats.service_fees)}</Text><Text style={ui.statLbl}>Frais svc</Text></View>
              <View style={ui.stat}><Text style={ui.statNum}>{fmt(stats.delivery_fees)}</Text><Text style={ui.statLbl}>Livraison</Text></View>
            </View>
          </>
        )}

        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: '#0A192F' }}>Revenus — {rev?.period}</Text>
          {revRows.map((d, i) => {
            const label = d.date || d.week || d.month || `#${i}`;
            const pct = Math.round((Number(d.total_revenue || 0) / maxRev) * 100);
            return (
              <View key={i} style={{ marginTop: 8 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                  <Text style={ui.subtitle}>{label}</Text>
                  <Text style={{ fontWeight: '700', color: '#0A192F', fontSize: 12 }}>{fmt(d.total_revenue)}</Text>
                </View>
                <View style={{ height: 8, backgroundColor: '#F1F5F9', borderRadius: 99, marginTop: 4 }}>
                  <View style={{ height: 8, width: `${pct}%`, backgroundColor: '#FD761A', borderRadius: 99 }} />
                </View>
              </View>
            );
          })}
          <Text style={[ui.subtitle, { marginTop: 8 }]}>
            Total période : {fmt(rev?.totals?.total_revenue)} • {rev?.totals?.total_orders} commandes
          </Text>
        </View>

        {!!metrics && (
          <View style={ui.card}>
            <Text style={{ fontWeight: '800', color: '#0A192F' }}>Livraisons</Text>
            <Text style={ui.subtitle}>
              {metrics.completed}/{metrics.total_deliveries} terminées • {metrics.waiting_pickup} en attente de retrait • {metrics.in_progress} en cours
            </Text>
          </View>
        )}

        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: '#0A192F' }}>Commandes récentes</Text>
          {(stats?.recent_orders || []).map((o: any) => (
            <View key={o.id} style={{ flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6 }}>
              <Text style={{ color: '#0A192F' }}>#{o.id} • {o.client_name}</Text>
              <Text style={ui.subtitle}>{Number(o.total_amount).toLocaleString('fr-FR')} F • {o.status}</Text>
            </View>
          ))}
        </View>
      </ScrollView>
    </View>
  );
}
