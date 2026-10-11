import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, RefreshControl } from 'react-native';
import { api, fmt, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui } from '../../lib/ui';
import { TopBar } from '../../components/chrome';

const GOAL = 12;

export default function LivreurRapports() {
  const { token, driverId, handleUnauthorized } = useAuth();
  const [data, setData] = useState<any | null>(null);
  const [err, setErr] = useState('');
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token || !driverId) {
      setRefreshing(false);
      return;
    }
    try {
      const d = await api(`/delivery/earnings?driver_id=${driverId}`, { token });
      setData(d);
      setErr('');
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
      else setErr(e?.message || 'Chargement impossible.');
    } finally {
      setRefreshing(false);
    }
  }, [token, driverId, handleUnauthorized]);

  useEffect(() => {
    const t = setTimeout(() => {
      load();
    }, 0);
    return () => clearTimeout(t);
  }, [load]);

  const count = data?.history?.length || 0;
  const pct = Math.min(100, Math.round((count / GOAL) * 100));

  return (
    <View style={ui.page}>
      <TopBar title="Rapports & Paiements" subtitle="Gains du shift" />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        {!!err && <Text style={ui.err}>{err}</Text>}
        {!driverId && <Text style={ui.err}>Compte livreur non lié. Contacte la régie.</Text>}
        {!!data && (
          <>
            <View style={ui.hero}>
              <Text style={ui.heroSub}>Gains nets</Text>
              <Text style={ui.heroBig}>{fmt(data.earned)}</Text>
              <View style={{ flexDirection: 'row', gap: 8, marginTop: 8 }}>
                <View style={ui.badgeGreen}><Text style={ui.badgeTextGreen}>Reçu : {fmt(data.received)}</Text></View>
                <View style={ui.badgeAmber}><Text style={ui.badgeTextAmber}>En attente : {fmt(data.pending)}</Text></View>
              </View>
              <Text style={{ color: '#FBBF24', marginTop: 8, fontSize: 12 }}>Reversement avant 23h30 auprès du régisseur</Text>
            </View>
            <View style={ui.card}>
              <View style={ui.row}>
                <Text style={{ fontWeight: '800', color: '#0A192F' }}>Objectif : {count}/{GOAL} courses</Text>
                <Text style={ui.subtitle}>{pct}%</Text>
              </View>
              <View style={{ height: 8, backgroundColor: '#F1F5F9', borderRadius: 99, marginTop: 8 }}>
                <View style={{ height: 8, width: `${pct}%`, backgroundColor: '#FD761A', borderRadius: 99 }} />
              </View>
              <Text style={[ui.subtitle, { marginTop: 6 }]}>+3 000 FCFA de bonus si 12 courses • Niveau 1</Text>
            </View>
            <View style={ui.card}>
              <Text style={{ fontWeight: '800', color: '#0A192F' }}>Historique</Text>
              {data.history.map((h: any) => (
                <View key={h.id} style={[ui.row, { paddingVertical: 6 }]}>
                  <Text style={{ color: '#0A192F' }}>#OP-{h.id} • {h.neighborhood}</Text>
                  <Text style={ui.subtitle}>{Number(h.driver_amount).toLocaleString('fr-FR')} F • {h.driver_paid ? 'Validé' : 'Clôturé'}</Text>
                </View>
              ))}
            </View>
          </>
        )}
      </ScrollView>
    </View>
  );
}
