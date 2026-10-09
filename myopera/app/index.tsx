import { Redirect } from 'expo-router';
import { ActivityIndicator, View } from 'react-native';
import { useAuth } from '../lib/auth';
import { C } from '../lib/ui';

export default function Index() {
  const { token, role, ready } = useAuth();
  if (!ready) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg }}>
        <ActivityIndicator color={C.orange} />
      </View>
    );
  }
  if (!token) return <Redirect href="/login" />;
  if (role === 'DELIVERY') return <Redirect href="/login" />;
  return <Redirect href="/(admin)/commandes" />;
}
