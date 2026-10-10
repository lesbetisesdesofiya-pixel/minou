import { Tabs, Redirect } from 'expo-router';
import { View, ActivityIndicator } from 'react-native';
import { Receipt, UtensilsCrossed, Bike, ChartColumn } from 'lucide-react-native';
import { useAuth } from '../../lib/auth';
import { C } from '../../lib/ui';

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
        options={{ title: 'Commandes', tabBarIcon: ({ color, size }) => <Receipt color={color} size={size} /> }}
      />
      <Tabs.Screen
        name="carte"
        options={{ title: 'Menu & Stock', tabBarIcon: ({ color, size }) => <UtensilsCrossed color={color} size={size} /> }}
      />
      <Tabs.Screen
        name="livreurs"
        options={{ title: 'Livreurs', tabBarIcon: ({ color, size }) => <Bike color={color} size={size} /> }}
      />
      <Tabs.Screen
        name="rapports"
        options={{ title: 'Rapports', tabBarIcon: ({ color, size }) => <ChartColumn color={color} size={size} /> }}
      />
    </Tabs>
  );
}
