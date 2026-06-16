import { BASE_URL } from "../config/config";

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
  const response = await fetch(`${BASE_URL}api_ocupacional_empresas.php?${params.toString()}`);
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
  const response = await fetch(`${BASE_URL}api_ocupacional_empresas.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function inactivarEmpresaOcupacional(id) {
  const response = await fetch(`${BASE_URL}api_ocupacional_empresas.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "inactivar", id }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function crearEmpresaOcupacional(data) {
  const response = await fetch(`${BASE_URL}api_ocupacional_empresas.php`, {
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

  const response = await fetch(`${BASE_URL}api_ocupacional_consultar_identidad.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function registrarTrabajadorOcupacional(data) {
  const response = await fetch(`${BASE_URL}api_ocupacional_trabajadores.php`, {
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
  const response = await fetch(`${BASE_URL}api_ocupacional_trabajadores.php?${params.toString()}`);
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
  const response = await fetch(`${BASE_URL}api_ocupacional_trabajadores.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function darBajaTrabajadorOcupacional(id) {
  const response = await fetch(`${BASE_URL}api_ocupacional_trabajadores_baja.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ id }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function listarExamenesOcupacionalesPaginado({
  estado = "activo",
  q = "",
  page = 1,
  perPage = 20,
  sortBy = "descripcion",
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
  const response = await fetch(`${BASE_URL}api_ocupacional_examenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function crearExamenOcupacional(data) {
  const response = await fetch(`${BASE_URL}api_ocupacional_examenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify(data),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function actualizarExamenOcupacional(data) {
  const response = await fetch(`${BASE_URL}api_ocupacional_examenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify(data),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function inactivarExamenOcupacional(id) {
  const response = await fetch(`${BASE_URL}api_ocupacional_examenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "inactivar", id }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function listarCatalogoEmpresaExamenesPaginado({
  empresaId,
  estadoCatalogo = "todos",
  q = "",
  page = 1,
  perPage = 50,
} = {}) {
  const params = new URLSearchParams({
    empresa_id: String(empresaId || 0),
    estado_catalogo: estadoCatalogo,
    q,
    page: String(page),
    per_page: String(perPage),
  });

  const response = await fetch(`${BASE_URL}api_ocupacional_catalogo.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function actualizarCatalogoEmpresaExamen({ empresaId, examenId, habilitado }) {
  const response = await fetch(`${BASE_URL}api_ocupacional_catalogo.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      empresa_id: Number(empresaId),
      examen_id: Number(examenId),
      habilitado: Boolean(habilitado),
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function listarTiposEvaluacionOcupacional() {
  const params = new URLSearchParams({ accion: "tipos" });
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function listarProtocolosOcupacionales({ empresaId, estado = "activo" } = {}) {
  const params = new URLSearchParams({
    accion: "listar_protocolos",
    empresa_id: String(empresaId || 0),
    estado,
  });
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function guardarProtocoloOcupacional({ id, empresaId, descripcion }) {
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      accion: "guardar_protocolo",
      ...(id ? { id: Number(id) } : {}),
      empresa_id: Number(empresaId),
      descripcion: String(descripcion || "").trim(),
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function inactivarProtocoloOcupacional(id) {
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "inactivar_protocolo", id: Number(id) }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function listarMatrizProtocoloOcupacional({
  empresaId,
  protocoloId,
  q = "",
  page = 1,
  perPage = 50,
} = {}) {
  const params = new URLSearchParams({
    accion: "listar_matriz",
    empresa_id: String(empresaId || 0),
    protocolo_id: String(protocoloId || 0),
    q,
    page: String(page),
    per_page: String(perPage),
  });
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    tipos: payload.tipos || [],
    data: payload.data || [],
    totales: payload.totales || {},
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function guardarMontoProtocoloOcupacional({
  protocoloId,
  catalogoId,
  tipoEvaluacionId,
  monto,
} = {}) {
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      accion: "guardar_monto",
      protocolo_id: Number(protocoloId),
      catalogo_id: Number(catalogoId),
      tipo_evaluacion_id: Number(tipoEvaluacionId),
      monto,
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function listarPuestosOcupacionalesEmpresa(empresaId) {
  const params = new URLSearchParams({
    accion: "listar_puestos",
    empresa_id: String(empresaId || 0),
  });
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function listarCondicionesProtocoloOcupacional({ protocoloId, catalogoId } = {}) {
  const params = new URLSearchParams({
    accion: "listar_condiciones",
    protocolo_id: String(protocoloId || 0),
    catalogo_id: String(catalogoId || 0),
  });
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function guardarCondicionProtocoloOcupacional({
  id,
  protocoloId,
  catalogoId,
  puestoTrabajo,
  sexo,
  edadMin,
  edadMax,
} = {}) {
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      accion: "guardar_condicion",
      ...(id ? { id: Number(id) } : {}),
      protocolo_id: Number(protocoloId),
      catalogo_id: Number(catalogoId),
      puesto_trabajo: puestoTrabajo || "",
      sexo: sexo || "",
      edad_min: edadMin ?? "",
      edad_max: edadMax ?? "",
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function eliminarCondicionProtocoloOcupacional(id) {
  const response = await fetch(`${BASE_URL}api_ocupacional_protocolos.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "eliminar_condicion", id: Number(id) }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function previsualizarOrdenOcupacional({
  empresaId,
  trabajadorId,
  protocoloId,
  tipoEvaluacionId,
} = {}) {
  const params = new URLSearchParams({
    accion: "previsualizar",
    empresa_id: String(empresaId || 0),
    trabajador_id: String(trabajadorId || 0),
    protocolo_id: String(protocoloId || 0),
    tipo_evaluacion_id: String(tipoEvaluacionId || 0),
  });
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function registrarOrdenOcupacional({
  empresaId,
  trabajadorId,
  protocoloId,
  tipoEvaluacionId,
  fechaOrden,
  observacion,
} = {}) {
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      accion: "registrar_orden",
      empresa_id: Number(empresaId),
      trabajador_id: Number(trabajadorId),
      protocolo_id: Number(protocoloId),
      tipo_evaluacion_id: Number(tipoEvaluacionId),
      fecha_orden: String(fechaOrden || "").trim(),
      observacion: String(observacion || "").trim(),
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function listarOrdenesOcupacionalesPaginado({
  empresaId = 0,
  estado = "",
  tipo = "",
  fechaDesde = "",
  fechaHasta = "",
  q = "",
  page = 1,
  perPage = 20,
} = {}) {
  const params = new URLSearchParams({
    accion: "listar_ordenes",
    q,
    page: String(page),
    per_page: String(perPage),
  });
  if (Number(empresaId) > 0) {
    params.set("empresa_id", String(empresaId));
  }
  if (String(estado || "").trim() !== "") {
    params.set("estado", String(estado).trim());
  }
  if (String(tipo || "").trim() !== "") {
    params.set("tipo", String(tipo).trim());
  }
  if (String(fechaDesde || "").trim() !== "") {
    params.set("fecha_desde", String(fechaDesde).trim());
  }
  if (String(fechaHasta || "").trim() !== "") {
    params.set("fecha_hasta", String(fechaHasta).trim());
  }
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return {
    data: payload.data || [],
    meta: payload.meta || { page, per_page: perPage, total: 0, total_pages: 0 },
  };
}

export async function obtenerDetalleOrdenOcupacional(id) {
  const params = new URLSearchParams({
    accion: "detalle_orden",
    id: String(Number(id) || 0),
  });
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function anularOrdenOcupacional(id, motivo = "") {
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "anular_orden", id: Number(id), motivo: String(motivo || "").trim() }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function cerrarOrdenOcupacional(id) {
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({ accion: "cerrar_orden", id: Number(id) }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload;
}

export async function guardarAptitudOrdenOcupacional({
  id,
  aptitudFinal,
  restriccionFinal,
  recomendacionFinal,
  medicoResponsable,
} = {}) {
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      accion: "guardar_aptitud_orden",
      id: Number(id),
      aptitud_final: String(aptitudFinal || "").trim(),
      restriccion_final: String(restriccionFinal || "").trim(),
      recomendacion_final: String(recomendacionFinal || "").trim(),
      medico_responsable: String(medicoResponsable || "").trim(),
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function actualizarDetalleOrdenOcupacional({
  detalleId,
  estadoEjecucion,
  observacionEjecucion,
} = {}) {
  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php`, {
    method: "POST",
    headers: jsonHeaders,
    body: JSON.stringify({
      accion: "actualizar_detalle_orden",
      detalle_id: Number(detalleId),
      estado_ejecucion: String(estadoEjecucion || "").trim(),
      observacion_ejecucion: String(observacionEjecucion || "").trim(),
    }),
  });
  const payload = await parseJsonOrThrow(response);
  return payload.data;
}

export async function listarEventosOrdenOcupacional({
  ordenId,
  tipo = "",
  fechaDesde = "",
  fechaHasta = "",
  limit = 100,
} = {}) {
  const params = new URLSearchParams({
    accion: "eventos_orden",
    id: String(Number(ordenId) || 0),
    limit: String(Number(limit) || 100),
  });
  if (String(tipo || "").trim() !== "") {
    params.set("tipo", String(tipo).trim());
  }
  if (String(fechaDesde || "").trim() !== "") {
    params.set("fecha_desde", String(fechaDesde).trim());
  }
  if (String(fechaHasta || "").trim() !== "") {
    params.set("fecha_hasta", String(fechaHasta).trim());
  }

  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}

export async function obtenerResumenOrdenesOcupacionales({
  empresaId = 0,
  estado = "",
  tipo = "",
  fechaDesde = "",
  fechaHasta = "",
  q = "",
} = {}) {
  const params = new URLSearchParams({ accion: "resumen_ordenes", q });
  if (Number(empresaId) > 0) params.set("empresa_id", String(empresaId));
  if (String(estado || "").trim() !== "") params.set("estado", String(estado).trim());
  if (String(tipo || "").trim() !== "") params.set("tipo", String(tipo).trim());
  if (String(fechaDesde || "").trim() !== "") params.set("fecha_desde", String(fechaDesde).trim());
  if (String(fechaHasta || "").trim() !== "") params.set("fecha_hasta", String(fechaHasta).trim());

  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || null;
}

export async function obtenerReporteOrdenesOcupacionales({
  empresaId = 0,
  estado = "",
  tipo = "",
  fechaDesde = "",
  fechaHasta = "",
  q = "",
  limit = 2000,
} = {}) {
  const params = new URLSearchParams({
    accion: "reporte_ordenes",
    q,
    limit: String(Number(limit) || 2000),
  });
  if (Number(empresaId) > 0) params.set("empresa_id", String(empresaId));
  if (String(estado || "").trim() !== "") params.set("estado", String(estado).trim());
  if (String(tipo || "").trim() !== "") params.set("tipo", String(tipo).trim());
  if (String(fechaDesde || "").trim() !== "") params.set("fecha_desde", String(fechaDesde).trim());
  if (String(fechaHasta || "").trim() !== "") params.set("fecha_hasta", String(fechaHasta).trim());

  const response = await fetch(`${BASE_URL}api_ocupacional_ordenes.php?${params.toString()}`);
  const payload = await parseJsonOrThrow(response);
  return payload.data || [];
}
