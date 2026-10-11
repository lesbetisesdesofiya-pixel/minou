import { Tabs, Redirect } from 'expo-router';
import { Text, View, ActivityIndicator } from 'react-native';
import { Bike, User, Wallet } from 'lucide-react-native';
import { useAuth } from '../../lib/auth';
import { C } from '../../lib/ui';

export default function LivreurLayout() {
  const { token, role, ready } = useAuth();
  if (!ready) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg }}>
        <ActivityIndicator color={C.orange} />
      </View>
    );
  }
  if (!token) return <Redirect href="/login" />;

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
        options={{ title: 'Commandes', tabBarIcon: ({ color, size }) => <Bike color={color} size={size} /> }}
      />
      <Tabs.Screen
        name="rapports"
        options={{ title: 'Rapports', tabBarIcon: ({ color, size }) => <Wallet color={color} size={size} /> }}
      />
      <Tabs.Screen
        name="profil"
        options={{ title: 'Mon Profil', tabBarIcon: ({ color, size }) => <User color={color} size={size} /> }}
      />
    </Tabs>
  );
}
