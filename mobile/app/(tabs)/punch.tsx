import { useCallback, useState } from 'react';
import { useFocusEffect } from 'expo-router';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { ApiError, apiRequest } from '@/lib/api';

type JobOption = { value: string; label: string; type: string };
type PunchEmployee = {
  id: number;
  display_name: string;
  is_punched_in: boolean;
  open_job_title?: string | null;
  open_clock_in_at?: string | null;
};

type PunchBoardPayload = {
  employees: PunchEmployee[];
  jobs: JobOption[];
  employee_link_missing: boolean;
};

export default function PunchScreen() {
  const [data, setData] = useState<PunchBoardPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    const payload = await apiRequest<PunchBoardPayload>('/api/v1/punch-board');
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

  async function punchIn(employeeId: number) {
    const firstJob = data?.jobs.find((j) => j.type === 'job');
    const jobSelection = firstJob?.value ?? 'shop_time';
    setBusyId(employeeId);
    try {
      await apiRequest('/api/v1/punch/in', {
        method: 'POST',
        body: JSON.stringify({ employee_id: employeeId, job_selection: jobSelection }),
      });
      await load();
    } catch (error) {
      Alert.alert('Punch in failed', error instanceof ApiError ? error.message : 'Try again');
    } finally {
      setBusyId(null);
    }
  }

  async function punchOut(employeeId: number) {
    setBusyId(employeeId);
    try {
      await apiRequest('/api/v1/punch/out', {
        method: 'POST',
        body: JSON.stringify({ employee_id: employeeId }),
      });
      await load();
    } catch (error) {
      Alert.alert('Punch out failed', error instanceof ApiError ? error.message : 'Try again');
    } finally {
      setBusyId(null);
    }
  }

  if (loading && !data) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  if (data?.employee_link_missing) {
    return (
      <View style={styles.centerPad}>
        <Text style={styles.warn}>No employee profile is linked to your account. Ask an admin to link one.</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#0d6efd" />}
    >
      {(data?.employees ?? []).map((employee) => (
        <View key={employee.id} style={styles.card}>
          <Text style={styles.name}>{employee.display_name}</Text>
          {employee.is_punched_in ? (
            <>
              <Text style={styles.meta}>
                In since {employee.open_clock_in_at ?? '—'} · {employee.open_job_title ?? 'Non-job'}
              </Text>
              <Pressable
                style={[styles.button, styles.outButton]}
                onPress={() => punchOut(employee.id)}
                disabled={busyId === employee.id}
              >
                <Text style={styles.buttonText}>{busyId === employee.id ? '…' : 'Punch out'}</Text>
              </Pressable>
            </>
          ) : (
            <Pressable
              style={[styles.button, styles.inButton]}
              onPress={() => punchIn(employee.id)}
              disabled={busyId === employee.id}
            >
              <Text style={styles.buttonText}>{busyId === employee.id ? '…' : 'Punch in'}</Text>
            </Pressable>
          )}
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  centerPad: { flex: 1, padding: 24, justifyContent: 'center' },
  warn: { color: '#b45309', fontSize: 16, lineHeight: 22 },
  card: {
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  name: { fontSize: 18, fontWeight: '700', color: '#0f172a' },
  meta: { color: '#64748b', marginTop: 6, marginBottom: 12 },
  button: { borderRadius: 10, paddingVertical: 14, alignItems: 'center' },
  inButton: { backgroundColor: '#16a34a' },
  outButton: { backgroundColor: '#dc2626' },
  buttonText: { color: '#fff', fontWeight: '600', fontSize: 16 },
});
