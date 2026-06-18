import { useCallback, useState } from 'react';
import { Link, useFocusEffect } from 'expo-router';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { apiRequest } from '@/lib/api';
import { useAuth } from '@/lib/auth';

type TodayPayload = {
  date: string;
  upcoming_schedule: Array<Record<string, unknown>>;
  past_due_schedule: Array<Record<string, unknown>>;
  open_entry: { job_title?: string | null; clock_in_at?: string } | null;
};

export default function TodayScreen() {
  const { session } = useAuth();
  const [data, setData] = useState<TodayPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    const payload = await apiRequest<TodayPayload>('/api/v1/dashboard/today');
    setData(payload);
  }, []);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      load()
        .catch(() => setData(null))
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

  if (loading && !data) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  const upcoming = data?.upcoming_schedule ?? [];
  const pastDue = data?.past_due_schedule ?? [];

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#0d6efd" />}
    >
      <Text style={styles.greeting}>Hello, {session?.user.display_name ?? 'crew'}</Text>
      <Text style={styles.business}>{session?.business.name}</Text>

      {data?.open_entry ? (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>On the clock</Text>
          <Text style={styles.cardBody}>
            {data.open_entry.job_title ?? 'Non-job time'} · since {data.open_entry.clock_in_at}
          </Text>
        </View>
      ) : null}

      {pastDue.length > 0 ? (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Past due</Text>
          {pastDue.slice(0, 8).map((item, index) => (
            <ScheduleRow key={`past-${index}`} item={item} />
          ))}
        </View>
      ) : null}

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Upcoming</Text>
        {upcoming.length === 0 ? (
          <Text style={styles.empty}>Nothing scheduled ahead.</Text>
        ) : (
          upcoming.slice(0, 12).map((item, index) => <ScheduleRow key={`up-${index}`} item={item} />)
        )}
      </View>
    </ScrollView>
  );
}

function ScheduleRow({ item }: { item: Record<string, unknown> }) {
  const title = String(item.title ?? item.label ?? item.name ?? 'Scheduled item');
  const when = String(item.scheduled_start_at ?? item.start_at ?? item.date ?? '');
  const jobId = Number(item.job_id ?? 0);

  const row = (
    <View style={styles.row}>
      <Text style={styles.rowTitle}>{title}</Text>
      {when ? <Text style={styles.rowMeta}>{when}</Text> : null}
    </View>
  );

  if (jobId > 0) {
    return (
      <Link href={`/job/${jobId}`} asChild>
        <Pressable>{row}</Pressable>
      </Link>
    );
  }

  return row;
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  greeting: { fontSize: 22, fontWeight: '700', padding: 16, paddingBottom: 4 },
  business: { color: '#64748b', paddingHorizontal: 16, marginBottom: 8 },
  card: {
    margin: 16,
    marginTop: 8,
    backgroundColor: '#dcfce7',
    borderRadius: 12,
    padding: 14,
  },
  cardTitle: { fontWeight: '700', color: '#166534' },
  cardBody: { color: '#15803d', marginTop: 4 },
  section: { paddingHorizontal: 16, paddingBottom: 16 },
  sectionTitle: { fontSize: 16, fontWeight: '700', marginBottom: 8, color: '#0f172a' },
  empty: { color: '#64748b' },
  row: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  rowTitle: { fontWeight: '600', color: '#0f172a' },
  rowMeta: { color: '#64748b', marginTop: 4, fontSize: 13 },
});
