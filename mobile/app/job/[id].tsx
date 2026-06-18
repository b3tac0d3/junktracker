import { useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { apiRequest } from '@/lib/api';
import { useAuth } from '@/lib/auth';

type JobDetail = {
  id: number;
  title: string;
  status: string;
  scheduled_start_at?: string | null;
  client_name?: string;
  client_phone?: string;
  notes?: string;
  address?: { formatted?: string };
  maps_directions_url?: string;
  client_phone_url?: string | null;
};

export default function JobDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { session } = useAuth();
  const [job, setJob] = useState<JobDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    const payload = await apiRequest<{ job: JobDetail }>(`/api/v1/jobs/${id}`);
    setJob(payload.job);
  }, [id]);

  useEffect(() => {
    setLoading(true);
    load()
      .catch(() => setJob(null))
      .finally(() => setLoading(false));
  }, [load]);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  if (!job) {
    return (
      <View style={styles.centerPad}>
        <Text>Job not found.</Text>
      </View>
    );
  }

  const label = session?.label_job ?? 'Job';

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.title}>{job.title}</Text>
      <Text style={styles.meta}>
        {label} #{job.id} · {job.status}
      </Text>

      {job.scheduled_start_at ? <Text style={styles.line}>Scheduled: {job.scheduled_start_at}</Text> : null}
      {job.client_name ? <Text style={styles.line}>Client: {job.client_name}</Text> : null}
      {job.address?.formatted ? <Text style={styles.line}>{job.address.formatted}</Text> : null}
      {job.notes ? <Text style={styles.notes}>{job.notes}</Text> : null}

      <View style={styles.actions}>
        {job.client_phone_url ? (
          <Pressable style={styles.button} onPress={() => Linking.openURL(job.client_phone_url!)}>
            <Text style={styles.buttonText}>Call client</Text>
          </Pressable>
        ) : null}
        {job.maps_directions_url ? (
          <Pressable style={[styles.button, styles.secondary]} onPress={() => Linking.openURL(job.maps_directions_url!)}>
            <Text style={[styles.buttonText, styles.secondaryText]}>Directions</Text>
          </Pressable>
        ) : null}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc', padding: 16 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  centerPad: { flex: 1, padding: 24 },
  title: { fontSize: 24, fontWeight: '700', color: '#0f172a' },
  meta: { color: '#64748b', marginTop: 6, marginBottom: 16 },
  line: { color: '#334155', marginBottom: 8, fontSize: 15 },
  notes: { color: '#475569', marginTop: 8, lineHeight: 20 },
  actions: { marginTop: 24, gap: 10 },
  button: { backgroundColor: '#0d6efd', borderRadius: 10, paddingVertical: 14, alignItems: 'center' },
  buttonText: { color: '#fff', fontWeight: '600' },
  secondary: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#0d6efd' },
  secondaryText: { color: '#0d6efd' },
});
