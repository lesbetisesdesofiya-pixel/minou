import { Stack } from 'expo-router';
import { AuthProvider } from '../lib/auth';
import { SiteProvider } from '../lib/site';

export default function RootLayout() {
  return (
    <SiteProvider>
      <AuthProvider>
        <Stack screenOptions={{ headerShown: false }}>
          <Stack.Screen name="index" />
          <Stack.Screen name="site-select" />
          <Stack.Screen name="login" />
          <Stack.Screen name="(admin)" />
        </Stack>
      </AuthProvider>
    </SiteProvider>
  );
}
