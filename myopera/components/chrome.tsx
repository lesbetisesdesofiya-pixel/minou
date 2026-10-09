import { View, Text, Pressable } from 'react-native';
import type { TextStyle, ViewStyle } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../lib/auth';
import { ui, C } from '../lib/ui';
import { STATUS_LABEL } from '../lib/api';

export function TopBar({ title, subtitle, live, right }: { title: string; subtitle?: string; live?: boolean; right?: React.ReactNode }) {
  const { logout } = useAuth();
  return (
    <View style={{ backgroundColor: '#fff', borderBottomColor: '#E2E8F0', borderBottomWidth: 1, padding: 12, flexDirection: 'row', alignItems: 'center', gap: 10 }}>
      <View style={{ width: 38, height: 38, borderRadius: 12, backgroundColor: C.navy, alignItems: 'center', justifyContent: 'center' }}>
        <Text style={{ color: '#fff', fontWeight: '800', fontSize: 19 }}>O</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text style={{ fontWeight: '800', color: C.navy, fontSize: 16 }}>{title}</Text>
        {!!subtitle && <Text style={{ color: C.muted, fontSize: 12 }}>{subtitle}</Text>}
      </View>
      {live && (
        <View style={[ui.badgeGreen, { flexDirection: 'row', alignItems: 'center', gap: 4 }]}>
          <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: '#22C55E' }} />
          <Text style={ui.badgeTextGreen}>En direct</Text>
        </View>
      )}
      {right}
      <Pressable
        style={[ui.btnGhost, ui.btnSm]}
        onPress={async () => {
          await logout();
          router.replace('/login');
        }}
      >
        <Text style={ui.btnTextDark}>⏻</Text>
      </Pressable>
    </View>
  );
}

const badgeStyle: Record<string, ViewStyle> = {
  pending: ui.badgeAmber,
  'En attente de paiement': ui.badgeAmber,
  'Payée': ui.badgeBlue,
  PREPARING: ui.badgeAmber,
  READY_FOR_PICKUP: ui.badgeGreen,
  delivered: ui.badgeGreen,
  'Annulée': ui.badgeGrey,
  cancelled: ui.badgeGrey,
};
const badgeTextStyle: Record<string, TextStyle> = {
  pending: ui.badgeTextAmber,
  'En attente de paiement': ui.badgeTextAmber,
  'Payée': ui.badgeTextBlue,
  PREPARING: ui.badgeTextAmber,
  READY_FOR_PICKUP: ui.badgeTextGreen,
  delivered: ui.badgeTextGreen,
  'Annulée': ui.badgeTextGrey,
  cancelled: ui.badgeTextGrey,
};

export function StatusBadge({ status }: { status: string }) {
  const b = badgeStyle[status] || ui.badgeGrey;
  const t = badgeTextStyle[status] || ui.badgeTextGrey;
  return (
    <View style={b}>
      <Text style={t}>{STATUS_LABEL[status] || status}</Text>
    </View>
  );
}
