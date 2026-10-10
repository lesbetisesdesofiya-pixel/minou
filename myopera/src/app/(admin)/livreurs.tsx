import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, Pressable, RefreshControl, TextInput, Modal, Linking } from 'react-native';
import { Phone, Search } from 'lucide-react-native';
import { api, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui, C } from '../../lib/ui';
import { TopBar } from '../../components/chrome';

export default function Livreurs() {
  const { token, handleUnauthorized } = useAuth();
  const [drivers, setDrivers] = useState<any[]>([]);
  const [metrics, setMetrics] = useState<any | null>(null);
  const [filter, setFilter] = useState('all');
  const [q, setQ] = useState('');
  const [err, setErr] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [showAdd, setShowAdd] = useState(false);
  const [form, setForm] = useState({ first_name: '', last_name: '', email: '', phone: '' });

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [persons, m] = await Promise.all([
        api('/delivery/persons', { token }),
        api('/analytics/deliveries/metrics', { token }).catch(() => null),
      ]);
      setDrivers(Array.isArray(persons) ? persons : []);
      setMetrics(m);
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

  const toggleActive = async (d: any) => {
    if (!token) return;
    try {
      await api(`/delivery/persons/${d.id}`, { method: 'PATCH', token, body: { active: !d.active } });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Action impossible.');
    }
  };

  const toggleSuspended = async (d: any) => {
    if (!token) return;
    try {
      await api(`/delivery/persons/${d.id}`, { method: 'PATCH', token, body: { suspended: !d.suspended } });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Action impossible.');
    }
  };

  const add = async () => {
    if (!token) return;
    try {
      await api('/delivery/persons', { method: 'POST', token, body: form });
      setShowAdd(false);
      setForm({ first_name: '', last_name: '', email: '', phone: '' });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Ajout impossible.');
    }
  };

  const list = drivers.filter((d) => {
    if (filter === 'active' && !d.active) return false;
    if (filter === 'paused' && (d.active || !d.suspended)) return false;
    if (filter === 'suspended' && !d.suspended) return false;
    return `${d.first_name} ${d.last_name} ${d.phone || ''}`.toLowerCase().includes(q.toLowerCase());
  });

  return (
    <View style={ui.page}>
      <TopBar title="Livreurs & Flotte" subtitle="Gestion des coursiers" />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        {!!err && <Text style={ui.err}>{err}</Text>}
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 8, paddingHorizontal: 14, height: 48 }}>
          <Search color={C.muted} size={16} />
          <TextInput style={{ flex: 1, fontSize: 15, color: C.navy }} placeholder="Rechercher un livreur…" value={q} onChangeText={setQ} />
        </View>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {[['all', 'Tous'], ['active', 'Disponibles'], ['paused', 'En pause'], ['suspended', 'Suspendus']].map(([k, l]) => (
            <Pressable key={k} style={[ui.chip, filter === k && ui.chipActive]} onPress={() => setFilter(k)}>
              <Text style={[ui.chipText, filter === k && ui.chipTextActive]}>{l}</Text>
            </Pressable>
          ))}
        </ScrollView>
        {!!metrics && (
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <View style={ui.stat}><Text style={ui.statNum}>{metrics.total_deliveries}</Text><Text style={ui.statLbl}>Livraisons</Text></View>
            <View style={ui.stat}><Text style={ui.statNum}>{metrics.completed}</Text><Text style={ui.statLbl}>Terminées</Text></View>
            <View style={ui.stat}><Text style={ui.statNum}>{metrics.in_progress}</Text><Text style={ui.statLbl}>En cours</Text></View>
          </View>
        )}
        <Pressable style={ui.btnOrange} onPress={() => setShowAdd(true)}>
          <Text style={ui.btnText}>+ Ajouter un livreur</Text>
        </Pressable>
        {list.map((d) => (
          <View style={ui.card} key={d.id}>
            <View style={ui.row}>
              <View style={{ flex: 1 }}>
                <Text style={{ fontWeight: '800', color: C.navy }}>{d.first_name} {d.last_name}</Text>
                <Text style={ui.subtitle}>{d.phone} • {d.email}</Text>
              </View>
              <View style={d.suspended ? ui.badgeGrey : d.active ? ui.badgeGreen : ui.badgeAmber}>
                <Text style={d.suspended ? ui.badgeTextGrey : d.active ? ui.badgeTextGreen : ui.badgeTextAmber}>
                  {d.suspended ? 'Suspendu' : d.active ? 'Disponible' : 'En pause'}
                </Text>
              </View>
            </View>
            <View style={[ui.row, { marginTop: 10 }]}>
              {!!d.phone && (
                <Pressable style={[ui.btnGhost, ui.btnSm]} onPress={() => Linking.openURL(`tel:${d.phone}`)}>
                  <Phone color={C.navy} size={16} />
                </Pressable>
              )}
              <Pressable style={[ui.btnGhost, ui.btnSm, { flex: 1 }]} onPress={() => toggleActive(d)}>
                <Text style={ui.btnTextDark}>{d.active ? 'Mettre en pause' : 'Activer'}</Text>
              </Pressable>
              <Pressable style={[ui.btnGhost, ui.btnSm, { flex: 1 }]} onPress={() => toggleSuspended(d)}>
                <Text style={ui.btnTextDark}>{d.suspended ? 'Réactiver' : 'Suspendre'}</Text>
              </Pressable>
            </View>
          </View>
        ))}
        {!!metrics?.per_driver && (
          <View style={ui.card}>
            <Text style={{ fontWeight: '800', color: C.navy }}>Performance flotte</Text>
            {metrics.per_driver.map((p: any) => (
              <View key={p.id} style={[ui.row, { paddingVertical: 6 }]}>
                <Text style={{ flex: 1, color: C.navy }}>{p.driver_name}</Text>
                <Text style={ui.subtitle}>{p.deliveries} courses • {Number(p.revenue_handled || 0).toLocaleString('fr-FR')} F</Text>
              </View>
            ))}
          </View>
        )}
      </ScrollView>

      <Modal visible={showAdd} transparent animationType="slide" onRequestClose={() => setShowAdd(false)}>
        <View style={{ flex: 1, backgroundColor: 'rgba(2,6,23,0.7)', justifyContent: 'center', padding: 20 }}>
          <View style={[ui.card, { gap: 4 }]}>
            <Text style={ui.title}>Nouveau livreur</Text>
            <Text style={ui.label}>Prénom</Text>
            <TextInput style={ui.input} value={form.first_name} onChangeText={(v) => setForm({ ...form, first_name: v })} />
            <Text style={ui.label}>Nom</Text>
            <TextInput style={ui.input} value={form.last_name} onChangeText={(v) => setForm({ ...form, last_name: v })} />
            <Text style={ui.label}>Email</Text>
            <TextInput style={ui.input} value={form.email} onChangeText={(v) => setForm({ ...form, email: v })} autoCapitalize="none" keyboardType="email-address" />
            <Text style={ui.label}>Téléphone</Text>
            <TextInput style={ui.input} value={form.phone} onChangeText={(v) => setForm({ ...form, phone: v })} placeholder="+225 …" keyboardType="phone-pad" />
            <View style={[ui.row, { marginTop: 12 }]}>
              <Pressable style={[ui.btnGhost, { flex: 1 }]} onPress={() => setShowAdd(false)}><Text style={ui.btnTextDark}>Annuler</Text></Pressable>
              <Pressable style={[ui.btnOrange, { flex: 1 }]} onPress={add}><Text style={ui.btnText}>Enregistrer</Text></Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}
