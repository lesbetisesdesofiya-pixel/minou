import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, Pressable, RefreshControl, TextInput, Modal } from 'react-native';
import { api, fmt, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui, C } from '../../lib/ui';
import { TopBar } from '../../components/chrome';

export default function Carte() {
  const { token, handleUnauthorized } = useAuth();
  const [view, setView] = useState<'plats' | 'poissons'>('plats');
  const [dishes, setDishes] = useState<any[]>([]);
  const [fish, setFish] = useState<any[]>([]);
  const [q, setQ] = useState('');
  const [err, setErr] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [showAdd, setShowAdd] = useState(false);
  const [form, setForm] = useState({ dish_id: '', name: '', price: '', stock_kg: '' });

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [menu, inv] = await Promise.all([
        api('/admin/menu', { token }),
        api('/inventory/fish', { token }),
      ]);
      setDishes(Array.isArray(menu) ? menu : []);
      setFish(Array.isArray(inv) ? inv : []);
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

  const toggleDish = async (d: any) => {
    if (!token) return;
    try {
      await api(`/dishes/${d.id}`, { method: 'PATCH', token, body: { active: !d.active } });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Action impossible.');
    }
  };

  const setStock = async (f: any, delta: number) => {
    if (!token) return;
    try {
      await api(`/inventory/fish/${f.id}`, { method: 'PATCH', token, body: { stock_kg: Math.max(0, Number(f.stock_kg || 0) + delta) } });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Action impossible.');
    }
  };

  const addFish = async () => {
    if (!token) return;
    try {
      await api('/inventory/fish', {
        method: 'POST',
        token,
        body: { dish_id: Number(form.dish_id), name: form.name, price: Number(form.price), stock_kg: Number(form.stock_kg) },
      });
      setShowAdd(false);
      setForm({ dish_id: '', name: '', price: '', stock_kg: '' });
      load();
    } catch (e: any) {
      setErr(e?.message || 'Ajout impossible.');
    }
  };

  const filtered = dishes.filter((d) => `${d.nom} ${d.category}`.toLowerCase().includes(q.toLowerCase()));

  return (
    <View style={ui.page}>
      <TopBar title="Menu & Stocks" subtitle="Carte des plats • Poissons frais" live />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        {!!err && <Text style={ui.err}>{err}</Text>}
        <View style={{ flexDirection: 'row' }}>
          {(['plats', 'poissons'] as const).map((v) => (
            <Pressable key={v} style={[ui.chip, view === v && ui.chipActive]} onPress={() => setView(v)}>
              <Text style={[ui.chipText, view === v && ui.chipTextActive]}>
                {v === 'plats' ? 'Carte des plats' : 'Stock poissons frais'}
              </Text>
            </Pressable>
          ))}
        </View>

        {view === 'plats' ? (
          <>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={ui.stat}><Text style={ui.statNum}>{dishes.length}</Text><Text style={ui.statLbl}>Total</Text></View>
              <View style={ui.stat}><Text style={ui.statNum}>{dishes.filter((d) => d.active).length}</Text><Text style={ui.statLbl}>Actifs</Text></View>
              <View style={ui.stat}><Text style={ui.statNum}>{dishes.filter((d) => !d.active).length}</Text><Text style={ui.statLbl}>Masqués</Text></View>
            </View>
            <TextInput style={ui.input} placeholder="🔍 Rechercher un plat…" value={q} onChangeText={setQ} />
            {filtered.map((d) => (
              <View style={ui.card} key={d.id}>
                <View style={ui.row}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontWeight: '800', color: C.navy }}>{d.nom}</Text>
                    <Text style={ui.subtitle}>{d.category} • {fmt(d.prix)}</Text>
                  </View>
                  <View style={d.active ? ui.badgeGreen : ui.badgeGrey}>
                    <Text style={d.active ? ui.badgeTextGreen : ui.badgeTextGrey}>{d.active ? 'Actif' : 'Masqué'}</Text>
                  </View>
                </View>
                <Pressable style={[ui.btnGhost, ui.btnSm, { marginTop: 10 }]} onPress={() => toggleDish(d)}>
                  <Text style={ui.btnTextDark}>{d.active ? 'Désactiver' : 'Activer'}</Text>
                </Pressable>
              </View>
            ))}
            <View style={ui.card}><Text style={ui.subtitle}>🔄 Toute activation / désactivation est immédiate sur le menu client.</Text></View>
          </>
        ) : (
          <>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={ui.stat}><Text style={ui.statNum}>{fish.length}</Text><Text style={ui.statLbl}>Variétés</Text></View>
              <View style={ui.stat}><Text style={ui.statNum}>{fish.filter((f) => Number(f.stock_kg) > 0).length}</Text><Text style={ui.statLbl}>Dispo</Text></View>
              <View style={ui.stat}><Text style={ui.statNum}>{fish.filter((f) => Number(f.stock_kg) <= 0).length}</Text><Text style={ui.statLbl}>Ruptures</Text></View>
            </View>
            <Pressable style={ui.btnOrange} onPress={() => setShowAdd(true)}>
              <Text style={ui.btnText}>+ Ajouter un poisson</Text>
            </Pressable>
            {fish.map((f) => (
              <View style={ui.card} key={f.id}>
                <View style={ui.row}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontWeight: '800', color: C.navy }}>{f.type_poisson}</Text>
                    <Text style={ui.subtitle}>{f.plat_nom} • {fmt(f.price)}</Text>
                  </View>
                  <View style={Number(f.stock_kg) <= 0 ? ui.badgeGrey : Number(f.stock_kg) < 5 ? ui.badgeAmber : ui.badgeGreen}>
                    <Text style={Number(f.stock_kg) <= 0 ? ui.badgeTextGrey : Number(f.stock_kg) < 5 ? ui.badgeTextAmber : ui.badgeTextGreen}>
                      {Number(f.stock_kg) <= 0 ? 'Épuisé' : `${f.stock_kg} kg`}
                    </Text>
                  </View>
                </View>
                <View style={[ui.row, { marginTop: 10 }]}>
                  <View style={[ui.row, { borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 999, paddingHorizontal: 6, paddingVertical: 4 }]}>
                    <Pressable style={[ui.btnGhost, ui.btnSm]} onPress={() => setStock(f, -1)}><Text style={ui.btnTextDark}>−</Text></Pressable>
                    <Text style={{ fontWeight: '800', color: C.navy, marginHorizontal: 10 }}>{f.stock_kg}</Text>
                    <Pressable style={[ui.btnGhost, ui.btnSm]} onPress={() => setStock(f, 1)}><Text style={ui.btnTextDark}>+</Text></Pressable>
                  </View>
                  <Pressable style={[ui.btnGhost, ui.btnSm]} onPress={() => setStock(f, 10)}>
                    <Text style={ui.btnTextDark}>+10</Text>
                  </Pressable>
                </View>
              </View>
            ))}
          </>
        )}
      </ScrollView>

      <Modal visible={showAdd} transparent animationType="slide" onRequestClose={() => setShowAdd(false)}>
        <View style={{ flex: 1, backgroundColor: 'rgba(2,6,23,0.7)', justifyContent: 'center', padding: 20 }}>
          <View style={[ui.card, { gap: 4 }]}>
            <Text style={ui.title}>Nouveau poisson</Text>
            <Text style={ui.label}>ID du plat (catégorie Poissons)</Text>
            <TextInput style={ui.input} value={form.dish_id} onChangeText={(v) => setForm({ ...form, dish_id: v })} keyboardType="numeric" placeholder="Ex: 3" />
            <Text style={ui.label}>Nom de la variation</Text>
            <TextInput style={ui.input} value={form.name} onChangeText={(v) => setForm({ ...form, name: v })} placeholder="Ex: Bar — Assinie" />
            <Text style={ui.label}>Prix (FCFA)</Text>
            <TextInput style={ui.input} value={form.price} onChangeText={(v) => setForm({ ...form, price: v })} keyboardType="numeric" />
            <Text style={ui.label}>Stock (kg)</Text>
            <TextInput style={ui.input} value={form.stock_kg} onChangeText={(v) => setForm({ ...form, stock_kg: v })} keyboardType="numeric" />
            <View style={[ui.row, { marginTop: 12 }]}>
              <Pressable style={[ui.btnGhost, { flex: 1 }]} onPress={() => setShowAdd(false)}><Text style={ui.btnTextDark}>Annuler</Text></Pressable>
              <Pressable style={[ui.btnOrange, { flex: 1 }]} onPress={addFish}><Text style={ui.btnText}>Enregistrer</Text></Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}
