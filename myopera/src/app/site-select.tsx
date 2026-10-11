import { View, Text, Pressable, ScrollView } from 'react-native';
import { router } from 'expo-router';
import { SITES, useSite } from '../lib/site';
import { useAuth } from '../lib/auth';
import { ui, C } from '../lib/ui';

// Choix unique du site AVANT le login (détermine l'URL de l'API).
// Plus jamais demandé ensuite (stocké), modifiable depuis l'écran login.
export default function SiteSelect() {
  const { chooseSite } = useSite();
  const { logout } = useAuth();

  const pick = async (id: string) => {
    await chooseSite(id);
    await logout(); // le token est lié à un site : on repart au login
    router.replace('/login');
  };

  return (
    <ScrollView style={ui.page} contentContainerStyle={{ padding: 20, justifyContent: 'center', flexGrow: 1 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
        <View style={{ width: 40, height: 40, borderRadius: 12, backgroundColor: C.navy, alignItems: 'center', justifyContent: 'center' }}>
          <Text style={{ color: '#fff', fontWeight: '800', fontSize: 20 }}>O</Text>
        </View>
        <Text style={ui.title}>Opéra Admin</Text>
      </View>
      <View style={[ui.card, { marginTop: 16, gap: 10 }]}>
        <Text style={ui.title}>Choisis ton restaurant</Text>
        <Text style={ui.subtitle}>Une seule fois : ça règle l'adresse de l'API pour toute l'app.</Text>
        {SITES.map((s) => (
          <Pressable key={s.id} style={[ui.btnNavy, { alignItems: 'flex-start', paddingHorizontal: 16 }]} onPress={() => pick(s.id)}>
            <Text style={[ui.btnText, { fontSize: 16 }]}>{s.label}</Text>
            <Text style={{ color: '#CBD5E1', fontSize: 12 }}>{s.host}</Text>
          </Pressable>
        ))}
      </View>
    </ScrollView>
  );
}
