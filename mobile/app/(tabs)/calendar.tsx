import { useCallback, useState } from 'react';
import { useFocusEffect } from 'expo-router';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { apiRequest } from '@/lib/api';

type CalendarEvent = {
  id?: string | number;
  title?: string;
  start?: string;
  end?: string;
  extendedProps?: Record<string, unknown>;
};

export default function CalendarScreen() {
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    const start = new Date().toISOString().slice(0, 10);
    const endDate = new Date();
    endDate.setDate(endDate.getDate() + 14);
    const end = endDate.toISOString().slice(0, 10);
    const payload = await apiRequest<{ events: CalendarEvent[] }>(
      `/api/v1/events/feed?start=${start}&end=${end}`,
    );
    setEvents(payload.events ?? []);
  }, []);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      load()
        .catch(() => setEvents([]))
        .finally(() => setLoading(false));
    }, [load]),
  );

  async function onRefresh() {
    setRefreshing(true);
    try {
      await load();
    } finally {
      setRefreshing(false);
    }
  }

  if (loading && events.length === 0) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#0d6efd" />}
    >
      <Text style={styles.subtitle}>Next 14 days</Text>
      {events.length === 0 ? (
        <Text style={styles.empty}>No events in this range.</Text>
      ) : (
        events.map((event, index) => (
          <View key={String(event.id ?? index)} style={styles.row}>
            <Text style={styles.title}>{event.title ?? 'Event'}</Text>
            <Text style={styles.meta}>
              {event.start ?? ''}
              {event.end ? ` → ${event.end}` : ''}
            </Text>
          </View>
        ))
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  subtitle: { padding: 16, paddingBottom: 8, color: '#64748b' },
  empty: { paddingHorizontal: 16, color: '#64748b' },
  row: {
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginBottom: 10,
    borderRadius: 10,
    padding: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  title: { fontWeight: '600', color: '#0f172a' },
  meta: { color: '#64748b', marginTop: 4, fontSize: 13 },
});
