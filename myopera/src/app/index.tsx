import { Redirect } from 'expo-router';
import { ActivityIndicator, View } from 'react-native';
import { useAuth } from '../lib/auth';
import { useSite } from '../lib/site';
import { C } from '../lib/ui';

export default function Index() {
  const { token, role, ready } = useAuth();
  const { site, siteReady } = useSite();
  if (!siteReady || !ready) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg }}>
        <ActivityIndicator color={C.orange} />
      </View>
    );
  }
  if (!site) return <Redirect href="/site-select" />;
  if (!token) return <Redirect href="/login" />;
  if (role === 'DELIVERY') return <Redirect href="/(livreur)/commandes" />;
  return <Redirect href="/(admin)/commandes" />;
}
