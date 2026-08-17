import * as FileSystem from 'expo-file-system';
import * as ImagePicker from 'expo-image-picker';
import * as Sharing from 'expo-sharing';
import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import FolderPickerModal from '../src/components/FolderPickerModal';
import PromptModal from '../src/components/PromptModal';
import {
  archivoEliminar,
  carpetaCrear,
  carpetaEliminar,
  carpetas as fetchCarpetas,
  listar,
  mover,
  subir,
  type NubeArchivo,
  type NubeBreadcrumb,
  type NubeCarpetaItem,
  type NubeCarpetaPlana,
} from '../src/api/nube';
import { apiBaseUrl } from '../src/api/client';
import { useAuth } from '../src/state/auth';

export default function ExplorerScreen() {
  const { serverUrl, token, usuario, signOut } = useAuth();
  const { folder: folderParam } = useLocalSearchParams<{ folder?: string }>();
  const folder = folderParam ?? '';

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [breadcrumbs, setBreadcrumbs] = useState<NubeBreadcrumb[]>([]);
  const [folders, setFolders] = useState<NubeCarpetaItem[]>([]);
  const [files, setFiles] = useState<NubeArchivo[]>([]);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [createFolderVisible, setCreateFolderVisible] = useState(false);
  const [movePickerVisible, setMovePickerVisible] = useState(false);
  const [carpetasDisponibles, setCarpetasDisponibles] = useState<NubeCarpetaPlana[]>([]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!serverUrl || !token) return;
    const data = await listar(serverUrl, token, folder);
    setBreadcrumbs(data.breadcrumbs);
    setFolders(data.folders);
    setFiles(data.files);
  }, [serverUrl, token, folder]);

  useEffect(() => {
    setLoading(true);
    setSelected(new Set());
    load().finally(() => setLoading(false));
  }, [load]);

  async function onRefresh() {
    setRefreshing(true);
    try {
      await load();
    } finally {
      setRefreshing(false);
    }
  }

  function goToFolder(nextFolder: string) {
    router.push({ pathname: '/', params: { folder: nextFolder } });
  }

  function toggleSelect(rel: string) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(rel)) {
        next.delete(rel);
      } else {
        next.add(rel);
      }
      return next;
    });
  }

  function openPreview(file: NubeArchivo) {
    if (!serverUrl) return;
    router.push({
      pathname: '/preview',
      params: { url: serverUrl + file.url, name: file.name, rel: file.rel },
    });
  }

  async function handleCreateFolder(nombre: string) {
    if (!serverUrl || !token) return;
    setCreateFolderVisible(false);
    setBusy(true);
    try {
      await carpetaCrear(serverUrl, token, folder, nombre);
      await load();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo crear la carpeta');
    } finally {
      setBusy(false);
    }
  }

  function confirmDeleteCurrentFolder() {
    Alert.alert('Eliminar carpeta', '¿Seguro que querés eliminar esta carpeta y todo su contenido?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Eliminar', style: 'destructive', onPress: deleteCurrentFolder },
    ]);
  }

  async function deleteCurrentFolder() {
    if (!serverUrl || !token || folder === '') return;
    setBusy(true);
    try {
      const result = await carpetaEliminar(serverUrl, token, folder);
      router.replace({ pathname: '/', params: { folder: result.carpeta_padre } });
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo eliminar la carpeta');
    } finally {
      setBusy(false);
    }
  }

  async function pickAndUpload(fromCamera: boolean) {
    const permission = fromCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permiso requerido', 'Necesitamos permiso para acceder a las fotos.');
      return;
    }

    const result = fromCamera
      ? await ImagePicker.launchCameraAsync({ quality: 0.9 })
      : await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ['images'],
          allowsMultipleSelection: true,
          quality: 0.9,
        });

    if (result.canceled || result.assets.length === 0 || !serverUrl || !token) {
      return;
    }

    setBusy(true);
    try {
      const fotos = result.assets.map((asset, index) => ({
        uri: asset.uri,
        name: asset.fileName || `foto_${Date.now()}_${index}.jpg`,
        type: asset.mimeType || 'image/jpeg',
      }));
      await subir(serverUrl, token, folder, fotos);
      await load();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo subir la foto');
    } finally {
      setBusy(false);
    }
  }

  async function openMovePicker() {
    if (!serverUrl || !token) return;
    setBusy(true);
    try {
      const data = await fetchCarpetas(serverUrl, token);
      setCarpetasDisponibles(data.carpetas.filter((c) => c.folder !== folder));
      setMovePickerVisible(true);
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudieron cargar las carpetas');
    } finally {
      setBusy(false);
    }
  }

  async function handleMoveTo(destino: string) {
    if (!serverUrl || !token) return;
    setMovePickerVisible(false);
    setBusy(true);
    try {
      await mover(serverUrl, token, Array.from(selected), destino);
      setSelected(new Set());
      await load();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo mover la selección');
    } finally {
      setBusy(false);
    }
  }

  function confirmDeleteSelected() {
    Alert.alert('Eliminar fotos', `¿Eliminar ${selected.size} foto(s) seleccionada(s)?`, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Eliminar', style: 'destructive', onPress: deleteSelected },
    ]);
  }

  async function deleteSelected() {
    if (!serverUrl || !token) return;
    setBusy(true);
    try {
      for (const rel of selected) {
        await archivoEliminar(serverUrl, token, rel);
      }
      setSelected(new Set());
      await load();
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo eliminar la selección');
    } finally {
      setBusy(false);
    }
  }

  async function downloadSelectedZip() {
    if (!serverUrl || !token) return;
    setBusy(true);
    try {
      const response = await fetch(`${apiBaseUrl(serverUrl)}/descargar_zip.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({ seleccion: Array.from(selected) }),
      });

      if (!response.ok) {
        throw new Error('No se pudo generar el ZIP');
      }

      const blob = await response.blob();
      const base64: string = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('No se pudo leer el archivo descargado'));
        reader.onloadend = () => {
          const result = reader.result as string;
          resolve(result.split(',')[1] ?? '');
        };
        reader.readAsDataURL(blob);
      });

      const fileUri = `${FileSystem.cacheDirectory}fotos_nube_${Date.now()}.zip`;
      await FileSystem.writeAsStringAsync(fileUri, base64, { encoding: FileSystem.EncodingType.Base64 });

      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(fileUri);
      }
      setSelected(new Set());
    } catch (err) {
      Alert.alert('Error', err instanceof Error ? err.message : 'No se pudo descargar el ZIP');
    } finally {
      setBusy(false);
    }
  }

  const selectionMode = selected.size > 0;

  return (
    <View style={styles.container}>
      <View style={styles.breadcrumbBar}>
        <FlatList
          horizontal
          data={breadcrumbs}
          keyExtractor={(item) => item.folder}
          showsHorizontalScrollIndicator={false}
          renderItem={({ item, index }) => (
            <View style={styles.breadcrumbItem}>
              {index > 0 && <Text style={styles.breadcrumbSep}>/</Text>}
              <Pressable onPress={() => goToFolder(item.folder)}>
                <Text style={index === breadcrumbs.length - 1 ? styles.breadcrumbActive : styles.breadcrumbLink}>
                  {item.label}
                </Text>
              </Pressable>
            </View>
          )}
        />
      </View>

      <View style={styles.toolbar}>
        <Pressable style={styles.toolbarButton} onPress={() => setCreateFolderVisible(true)}>
          <Text style={styles.toolbarButtonText}>+ Carpeta</Text>
        </Pressable>
        <Pressable style={styles.toolbarButton} onPress={() => pickAndUpload(false)}>
          <Text style={styles.toolbarButtonText}>Galería</Text>
        </Pressable>
        <Pressable style={styles.toolbarButton} onPress={() => pickAndUpload(true)}>
          <Text style={styles.toolbarButtonText}>Cámara</Text>
        </Pressable>
        {folder !== '' && (
          <Pressable style={[styles.toolbarButton, styles.toolbarButtonDanger]} onPress={confirmDeleteCurrentFolder}>
            <Text style={styles.toolbarButtonDangerText}>Eliminar carpeta</Text>
          </Pressable>
        )}
      </View>

      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={[...folders.map((f) => ({ type: 'folder' as const, data: f })), ...files.map((f) => ({ type: 'file' as const, data: f }))]}
          keyExtractor={(item) => (item.type === 'folder' ? `folder-${item.data.folder}` : `file-${item.data.rel}`)}
          numColumns={3}
          contentContainerStyle={styles.grid}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
          ListEmptyComponent={<Text style={styles.empty}>No hay carpetas ni fotos acá todavía.</Text>}
          renderItem={({ item }) =>
            item.type === 'folder' ? (
              <Pressable style={styles.cell} onPress={() => goToFolder(item.data.folder)}>
                <View style={styles.folderIcon}>
                  <Text style={{ fontSize: 32 }}>📁</Text>
                </View>
                <Text numberOfLines={1} style={styles.cellLabel}>
                  {item.data.name}
                </Text>
              </Pressable>
            ) : (
              <Pressable
                style={styles.cell}
                onPress={() => (selectionMode ? toggleSelect(item.data.rel) : openPreview(item.data))}
                onLongPress={() => toggleSelect(item.data.rel)}
              >
                <View style={[styles.photoWrap, selected.has(item.data.rel) && styles.photoSelected]}>
                  <Image source={{ uri: serverUrl ? serverUrl + item.data.url : undefined }} style={styles.photo} />
                </View>
                <Text numberOfLines={1} style={styles.cellLabel}>
                  {item.data.name}
                </Text>
              </Pressable>
            )
          }
        />
      )}

      {selectionMode && (
        <View style={styles.selectionBar}>
          <Text style={styles.selectionCount}>{selected.size} seleccionada(s)</Text>
          <View style={{ flexDirection: 'row', gap: 12 }}>
            <Pressable onPress={openMovePicker}>
              <Text style={styles.selectionAction}>Mover</Text>
            </Pressable>
            <Pressable onPress={downloadSelectedZip}>
              <Text style={styles.selectionAction}>Descargar</Text>
            </Pressable>
            <Pressable onPress={confirmDeleteSelected}>
              <Text style={[styles.selectionAction, { color: '#dc3545' }]}>Eliminar</Text>
            </Pressable>
            <Pressable onPress={() => setSelected(new Set())}>
              <Text style={styles.selectionAction}>Cancelar</Text>
            </Pressable>
          </View>
        </View>
      )}

      {busy && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color="#fff" />
        </View>
      )}

      <View style={styles.footer}>
        <Text style={styles.footerUser}>{usuario?.nombre || usuario?.usuario}</Text>
        <Pressable onPress={() => signOut()}>
          <Text style={styles.footerLogout}>Cerrar sesión</Text>
        </Pressable>
      </View>

      <PromptModal
        visible={createFolderVisible}
        title="Nueva carpeta"
        placeholder="Nombre de la carpeta"
        onCancel={() => setCreateFolderVisible(false)}
        onConfirm={handleCreateFolder}
      />
      <FolderPickerModal
        visible={movePickerVisible}
        carpetas={carpetasDisponibles}
        onCancel={() => setMovePickerVisible(false)}
        onSelect={handleMoveTo}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  breadcrumbBar: { paddingHorizontal: 12, paddingTop: 10 },
  breadcrumbItem: { flexDirection: 'row', alignItems: 'center' },
  breadcrumbSep: { marginHorizontal: 4, color: '#aaa' },
  breadcrumbLink: { color: '#0d6efd' },
  breadcrumbActive: { color: '#111', fontWeight: '600' },
  toolbar: { flexDirection: 'row', gap: 8, paddingHorizontal: 12, paddingVertical: 10, flexWrap: 'wrap' },
  toolbarButton: { backgroundColor: '#eef3ff', borderRadius: 8, paddingHorizontal: 12, paddingVertical: 8 },
  toolbarButtonText: { color: '#0d6efd', fontWeight: '600', fontSize: 13 },
  toolbarButtonDanger: { backgroundColor: '#fdecec' },
  toolbarButtonDangerText: { color: '#dc3545', fontWeight: '600', fontSize: 13 },
  grid: { paddingHorizontal: 8, paddingBottom: 90 },
  cell: { flex: 1 / 3, padding: 6, alignItems: 'center' },
  folderIcon: { width: 90, height: 90, alignItems: 'center', justifyContent: 'center' },
  photoWrap: { width: 90, height: 90, borderRadius: 10, overflow: 'hidden', backgroundColor: '#f0f0f0' },
  photoSelected: { borderWidth: 3, borderColor: '#0d6efd' },
  photo: { width: '100%', height: '100%' },
  cellLabel: { fontSize: 11, marginTop: 4, maxWidth: 90, textAlign: 'center' },
  empty: { textAlign: 'center', color: '#888', marginTop: 40 },
  selectionBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 44,
    backgroundColor: '#111',
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  selectionCount: { color: '#fff', fontWeight: '600' },
  selectionAction: { color: '#8ab4ff', fontWeight: '600' },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  footer: {
    height: 44,
    borderTopWidth: 1,
    borderTopColor: '#eee',
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 14,
  },
  footerUser: { color: '#666', fontSize: 12 },
  footerLogout: { color: '#dc3545', fontSize: 12, fontWeight: '600' },
});
