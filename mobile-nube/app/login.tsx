import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { login } from '../src/api/nube';
import { useAuth } from '../src/state/auth';

export default function LoginScreen() {
  const { serverUrl: savedServerUrl, signIn } = useAuth();
  const [serverUrl, setServerUrl] = useState(savedServerUrl ?? '');
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  async function handleSubmit() {
    if (serverUrl.trim() === '' || usuario.trim() === '' || password === '') {
      setError('Completá todos los campos');
      return;
    }

    setLoading(true);
    setError('');
    try {
      const normalizedUrl = serverUrl.trim().replace(/\/+$/, '');
      const result = await login(normalizedUrl, usuario.trim(), password);
      await signIn(normalizedUrl, result.token, result.usuario);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo iniciar sesión');
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <Text style={styles.title}>☁️ La Nube</Text>
      <Text style={styles.subtitle}>Ingresá con tu usuario del admin</Text>

      <TextInput
        style={styles.input}
        placeholder="URL del servidor (https://tudominio.com)"
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="url"
        value={serverUrl}
        onChangeText={setServerUrl}
      />
      <TextInput
        style={styles.input}
        placeholder="Usuario"
        autoCapitalize="none"
        autoCorrect={false}
        value={usuario}
        onChangeText={setUsuario}
      />
      <TextInput
        style={styles.input}
        placeholder="Contraseña"
        secureTextEntry
        value={password}
        onChangeText={setPassword}
      />

      {error !== '' && <Text style={styles.error}>{error}</Text>}

      <Pressable style={styles.button} onPress={handleSubmit} disabled={loading}>
        {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Ingresar</Text>}
      </Pressable>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', padding: 24, backgroundColor: '#f8fbff' },
  title: { fontSize: 32, textAlign: 'center', marginBottom: 4 },
  subtitle: { fontSize: 14, color: '#666', textAlign: 'center', marginBottom: 24 },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#dfe3ea',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    marginBottom: 12,
  },
  error: { color: '#dc3545', marginBottom: 12, textAlign: 'center' },
  button: {
    backgroundColor: '#0d6efd',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  buttonText: { color: '#fff', fontWeight: '600', fontSize: 16 },
});
