import { Tabs } from 'expo-router';
import { Pressable, Text } from 'react-native';
import { useAuth } from '@/lib/auth';

export default function TabLayout() {
  const { logout } = useAuth();

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: '#0d6efd',
        tabBarStyle: { backgroundColor: '#212529', borderTopColor: '#343a40' },
        headerStyle: { backgroundColor: '#212529' },
        headerTintColor: '#fff',
        headerRight: () => (
          <Pressable onPress={() => logout()} style={{ marginRight: 12 }}>
            <Text style={{ color: '#94a3b8' }}>Logout</Text>
          </Pressable>
        ),
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Today', tabBarLabel: 'Today' }} />
      <Tabs.Screen name="punch" options={{ title: 'Punch', tabBarLabel: 'Punch' }} />
      <Tabs.Screen name="calendar" options={{ title: 'Calendar', tabBarLabel: 'Calendar' }} />
    </Tabs>
  );
}
