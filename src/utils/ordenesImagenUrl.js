function isAbsoluteLike(url) {
  return /^(https?:\/\/|data:|blob:)/i.test(String(url || '').trim());
}

function normalizeProjectPath(pathname) {
  const raw = String(pathname || '').trim();
  if (!raw) return raw;
  if (raw.startsWith('/clinica-2demayo/')) {
    return `/2demayo-ocupacional/${raw.slice('/clinica-2demayo/'.length)}`;
  }
  return raw;
}

export function normalizeOrdenImagenUrl(rawUrl) {
  const raw = String(rawUrl || '').trim();
  if (!raw) return raw;
  if (isAbsoluteLike(raw)) return raw;

  const parsed = new URL(raw, window.location.origin);
  const isDevVite = /^517\d$/.test(String(window.location.port || ''));
  if (!isDevVite) return parsed.toString();

  const backendOrigin = `${window.location.protocol}//${window.location.hostname}`;
  const normalizedPath = normalizeProjectPath(parsed.pathname);
  const isPhpEndpoint = normalizedPath.includes('.php');
  const hasProjectPrefix = normalizedPath.startsWith('/2demayo-ocupacional/');

  if (isPhpEndpoint || hasProjectPrefix) {
    return `${backendOrigin}${normalizedPath}${parsed.search}${parsed.hash}`;
  }

  return parsed.toString();
}

export function normalizeOrdenImagenArchivos(archivos) {
  const list = Array.isArray(archivos) ? archivos : [];
  return list.map((arch) => ({
    ...arch,
    url: normalizeOrdenImagenUrl(arch?.url || ''),
  }));
}

export function normalizeOrdenesImagenData(ordenes) {
  const list = Array.isArray(ordenes) ? ordenes : [];
  return list.map((orden) => ({
    ...orden,
    archivos: normalizeOrdenImagenArchivos(orden?.archivos),
  }));
}
