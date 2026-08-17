import { apiRequest } from './client';
import type { NubeUsuario } from '../state/auth';

export type NubeArchivo = {
  name: string;
  rel: string;
  url: string;
  tamanio: number;
};

export type NubeCarpetaItem = {
  name: string;
  folder: string;
};

export type NubeCarpetaPlana = {
  folder: string;
  label: string;
};

export type NubeBreadcrumb = {
  label: string;
  folder: string;
};

export async function login(serverUrl: string, usuario: string, password: string) {
  return apiRequest<{ token: string; usuario: NubeUsuario }>(serverUrl, null, 'login.php', {
    method: 'POST',
    data: { usuario, password, dispositivo: 'app-movil' },
  });
}

export async function logout(serverUrl: string, token: string) {
  return apiRequest<{}>(serverUrl, token, 'logout.php', { method: 'POST' });
}

export async function listar(serverUrl: string, token: string, folder: string) {
  return apiRequest<{
    folder: string;
    breadcrumbs: NubeBreadcrumb[];
    folders: NubeCarpetaItem[];
    files: NubeArchivo[];
  }>(serverUrl, token, `listar.php?folder=${encodeURIComponent(folder)}`);
}

export async function carpetas(serverUrl: string, token: string) {
  return apiRequest<{ carpetas: NubeCarpetaPlana[] }>(serverUrl, token, 'carpetas.php');
}

export async function carpetaCrear(serverUrl: string, token: string, folder: string, carpetaNombre: string) {
  return apiRequest<{}>(serverUrl, token, 'carpeta_crear.php', {
    method: 'POST',
    data: { folder, carpeta_nombre: carpetaNombre },
  });
}

export async function carpetaEliminar(serverUrl: string, token: string, carpeta: string) {
  return apiRequest<{ carpeta_padre: string }>(serverUrl, token, 'carpeta_eliminar.php', {
    method: 'POST',
    data: { carpeta },
  });
}

export async function archivoEliminar(serverUrl: string, token: string, archivo: string) {
  return apiRequest<{}>(serverUrl, token, 'archivo_eliminar.php', {
    method: 'POST',
    data: { archivo },
  });
}

export async function mover(serverUrl: string, token: string, seleccion: string[], destinoCarpeta: string) {
  return apiRequest<{}>(serverUrl, token, 'mover.php', {
    method: 'POST',
    data: { seleccion, destino_carpeta: destinoCarpeta },
  });
}

export async function subir(serverUrl: string, token: string, folder: string, fotos: { uri: string; name: string; type: string }[]) {
  const formData = new FormData();
  formData.append('folder', folder);
  fotos.forEach((foto) => {
    // @ts-expect-error React Native FormData acepta este shape para archivos
    formData.append('fotos[]', { uri: foto.uri, name: foto.name, type: foto.type });
  });

  return apiRequest<{ archivos: string[] }>(serverUrl, token, 'subir.php', {
    method: 'POST',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}

export function descargarZipUrl(serverUrl: string): string {
  const base = serverUrl.replace(/\/+$/, '');
  return `${base}/ecommerce/api/nube/descargar_zip.php`;
}
