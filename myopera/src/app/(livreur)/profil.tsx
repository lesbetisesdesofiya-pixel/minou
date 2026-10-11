import { useCallback, useEffect, useState } from 'react';
import { View, Text, ScrollView, Pressable, RefreshControl } from 'react-native';
import { router } from 'expo-router';
import { api, UnauthorizedError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ui, C } from '../../lib/ui';
import { TopBar } from '../../components/chrome';

export default function LivreurProfil() {
  const { token, driverId, logout, handleUnauthorized } = useAuth();
  const [me, setMe] = useState<any | null>(null);
  const [driver, setDriver] = useState<any | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const m = await api('/me', { token });
      setMe(m);
      if (m?.delivery_person_id) {
        const persons = await api('/delivery/persons', { token });
        setDriver((persons || []).find((p: any) => p.id === m.delivery_person_id) || null);
      }
    } catch (e: any) {
      if (e instanceof UnauthorizedError) await handleUnauthorized();
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

  return (
    <View style={ui.page}>
      <TopBar title="Mon Profil" subtitle={me?.email || ''} />
      <ScrollView
        contentContainerStyle={ui.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
      >
        <View style={ui.card}>
          <Text style={{ fontWeight: '800', color: C.navy, fontSize: 16 }}>
            {driver ? `${driver.first_name} ${driver.last_name}` : 'Coursier'}
          </Text>
          <Text style={ui.subtitle}>{me?.email} • Rôle {me?.role}</Text>
          {!!driver && (
            <Text style={[ui.subtitle, { marginTop: 6 }]}>
              {driver.phone} • {driver.active ? 'Actif' : 'Inactif'}
            </Text>
          )}
          {!!driverId && <Text style={ui.subtitle}>ID coursier : {driverId}</Text>}
        </View>
        <Pressable
          style={ui.btnGhost}
          onPress={async () => {
            await logout();
            router.replace('/login');
          }}
        >
          <Text style={ui.btnTextDark}>Se déconnecter</Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}
