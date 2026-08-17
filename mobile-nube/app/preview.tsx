import * as FileSystem from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import ImageViewing from 'react-native-image-viewing';

export default function PreviewScreen() {
  const { url, name } = useLocalSearchParams<{ url: string; name: string; rel: string }>();
  const [downloading, setDownloading] = useState(false);

  async function handleShare() {
    if (!url) return;
    setDownloading(true);
    try {
      const fileUri = `${FileSystem.cacheDirectory}${name || 'foto.jpg'}`;
      const { uri } = await FileSystem.downloadAsync(url, fileUri);
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri);
      }
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo descargar la foto');
    } finally {
      setDownloading(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: '#000' }}>
      <ImageViewing
        images={url ? [{ uri: url }] : []}
        imageIndex={0}
        visible
        onRequestClose={() => router.back()}
        FooterComponent={() => (
          <View style={styles.footer}>
            <Text style={styles.name} numberOfLines={1}>
              {name}
            </Text>
            <Pressable style={styles.shareButton} onPress={handleShare} disabled={downloading}>
              {downloading ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Text style={styles.shareText}>Compartir / Guardar</Text>
              )}
            </Pressable>
          </View>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  footer: {
    paddingHorizontal: 16,
    paddingBottom: 24,
    paddingTop: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  name: { color: '#fff', flex: 1 },
  shareButton: { backgroundColor: '#0d6efd', borderRadius: 8, paddingHorizontal: 14, paddingVertical: 8 },
  shareText: { color: '#fff', fontWeight: '600' },
});
