import { Tabs, Redirect } from 'expo-router';
import { Text, View, ActivityIndicator } from 'react-native';
import { useAuth } from '../../lib/auth';
import { C } from '../../lib/ui';

const Ico = ({ c }: { c: string }) => <Text style={{ fontSize: 22 }}>{c}</Text>;

export default function AdminLayout() {
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

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: C.orange,
        tabBarInactiveTintColor: C.muted,
      }}
    >
      <Tabs.Screen
        name="commandes"
        options={{ title: 'Commandes', tabBarIcon: () => <Ico c="🧾" /> }}
      />
      <Tabs.Screen
        name="carte"
        options={{ title: 'Menu & Stock', tabBarIcon: () => <Ico c="🍽️" /> }}
      />
      <Tabs.Screen
        name="livreurs"
        options={{ title: 'Livreurs', tabBarIcon: () => <Ico c="🛵" /> }}
      />
      <Tabs.Screen
        name="rapports"
        options={{ title: 'Rapports', tabBarIcon: () => <Ico c="📊" /> }}
      />
      <Tabs.Screen name="commande" options={{ href: null, title: 'Détail' }} />
    </Tabs>
  );
}
