import { FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import type { NubeCarpetaPlana } from '../api/nube';

type Props = {
  visible: boolean;
  carpetas: NubeCarpetaPlana[];
  onCancel: () => void;
  onSelect: (folder: string) => void;
};

export default function FolderPickerModal({ visible, carpetas, onCancel, onSelect }: Props) {
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onCancel}>
      <View style={styles.backdrop}>
        <View style={styles.card}>
          <Text style={styles.title}>Mover a...</Text>
          <FlatList
            data={carpetas}
            keyExtractor={(item) => item.folder}
            style={{ maxHeight: 320 }}
            ListEmptyComponent={<Text style={styles.empty}>No hay otras carpetas creadas todavía.</Text>}
            renderItem={({ item }) => (
              <Pressable style={styles.item} onPress={() => onSelect(item.folder)}>
                <Text style={styles.itemText}>{item.label}</Text>
              </Pressable>
            )}
          />
          <Pressable style={styles.cancel} onPress={onCancel}>
            <Text style={styles.cancelText}>Cancelar</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.4)', justifyContent: 'flex-end' },
  card: { backgroundColor: '#fff', borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 20, maxHeight: '70%' },
  title: { fontSize: 16, fontWeight: '600', marginBottom: 12 },
  item: { paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
  itemText: { fontSize: 15 },
  empty: { color: '#888', paddingVertical: 12 },
  cancel: { paddingVertical: 12, alignItems: 'center' },
  cancelText: { color: '#0d6efd', fontWeight: '600' },
});
