import { useState } from 'react';
import { View, Text, TextInput, Pressable, ScrollView } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../lib/auth';
import { ui, C } from '../lib/ui';

export default function Login() {
  const { login, logout } = useAuth();
  const [email, setEmail] = useState('admin@opera.com');
  const [password, setPassword] = useState('');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setErr('');
    setBusy(true);
    try {
      const { role } = await login(email.trim(), password.trim());
      if (role === 'DELIVERY') {
        await logout();
        setErr("Compte livreur : cette app est réservée à l'administration.");
        return;
      }
      router.replace('/(admin)/commandes');
    } catch (e: any) {
      setErr(e?.message || 'Identifiants invalides.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <ScrollView style={ui.page} contentContainerStyle={{ padding: 20, justifyContent: 'center', flexGrow: 1 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
        <View style={{ width: 40, height: 40, borderRadius: 12, backgroundColor: C.navy, alignItems: 'center', justifyContent: 'center' }}>
          <Text style={{ color: '#fff', fontWeight: '800', fontSize: 20 }}>O</Text>
        </View>
        <Text style={ui.title}>Opéra Admin</Text>
      </View>
      <View style={[ui.card, { marginTop: 16 }]}>
        <Text style={[ui.badgeTextWhite, ui.badgeNavy, { alignSelf: 'flex-start' }]}>Accès réservé</Text>
        <Text style={[ui.title, { marginTop: 8 }]}>Connexion sécurisée</Text>
        <Text style={ui.subtitle}>Gérant de salle &amp; Chef de cuisine</Text>
        {!!err && <Text style={[ui.err, { marginTop: 10 }]}>{err}</Text>}
        <Text style={ui.label}>Email professionnel</Text>
        <TextInput style={ui.input} value={email} onChangeText={setEmail} autoCapitalize="none" autoCorrect={false} keyboardType="email-address" />
        <Text style={ui.label}>Mot de passe</Text>
        <TextInput style={ui.input} value={password} onChangeText={setPassword} secureTextEntry autoCapitalize="none" autoCorrect={false} />
        <View style={{ height: 14 }} />
        <Pressable style={ui.btnNavy} disabled={busy} onPress={submit}>
          <Text style={ui.btnText}>{busy ? 'Connexion…' : 'Se connecter'}</Text>
        </Pressable>
        <Text style={[ui.subtitle, { marginTop: 10 }]}>🔒 Token stocké chiffré (SecureStore)</Text>
      </View>
    </ScrollView>
  );
}
