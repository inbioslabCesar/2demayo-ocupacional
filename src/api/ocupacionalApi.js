const jsonHeaders = {
  "Content-Type": "application/json",
};

async function parseJsonOrThrow(response) {
  let payload = null;
  try {
    payload = await response.json();
  } catch (error) {
    payload = null;
  }

  if (!response.ok || !payload?.success) {
    const message = payload?.error || `Error HTTP ${response.status}`;
    throw new Error(message);
  }

  return payload;
}

export async function listarEmpresasOcupacionales({ estado = "activo" } = {}) {
  const params = new URLSearchParams({
    estado,
    page: "1",
    per_page: "200",
  });
  const response = await fetch(`/api_ocupacional_empresas.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function listarEmpresasOcupacionalesPaginado({
  estado = "activo",
  q = "",
  page = 1,
  perPage = 20,
  sortBy = "razon_social",
  sortDir = "asc",
} = {}) {
  const params = new URLSearchParams({
    estado,
    q,
    page: String(page),
    per_page: String(perPage),
    sort_by: sortBy,
    sort_dir: sortDir,
  });
  const response = await fetch(`/api_ocupacional_empresas.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function inactivarEmpresaOcupacional(id) {
  const response = await fetch("/api_ocupacional_empresas.php", {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "inactivar", id }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function crearEmpresaOcupacional(data) {
  const response = await fetch("/api_ocupacional_empresas.php", {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify(data),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function verificarIdentidadClinica({ documentoTipo, documentoNumero }) {
  const params = new URLSearchParams({
    documento_tipo: documentoTipo,
    documento_numero: documentoNumero,
  });

  const response = await fetch(`/api_ocupacional_consultar_identidad.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function registrarTrabajadorOcupacional(data) {
  const response = await fetch("/api_ocupacional_trabajadores.php", {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify(data),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function listarTrabajadoresOcupacionales({ estado = "todos" } = {}) {
  const params = new URLSearchParams({
    estado,
    page: "1",
    per_page: "200",
  });
  const response = await fetch(`/api_ocupacional_trabajadores.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function listarTrabajadoresOcupacionalesPaginado({
  estado = "todos",
  q = "",
  empresaId = 0,
  page = 1,
  perPage = 20,
  sortBy = "fecha_ingreso",
  sortDir = "desc",
} = {}) {
  const params = new URLSearchParams({
    estado,
    q,
    page: String(page),
    per_page: String(perPage),
    sort_by: sortBy,
    sort_dir: sortDir,
  });
  if (Number(empresaId) > 0) {
    params.set("empresa_id", String(empresaId));
  }
  const response = await fetch(`/api_ocupacional_trabajadores.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function darBajaTrabajadorOcupacional(id) {
  const response = await fetch("/api_ocupacional_trabajadores_baja.php", {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ id }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}
