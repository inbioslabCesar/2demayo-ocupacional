import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  listarEmpresasOcupacionales,
  listarProtocolosOcupacionales,
  listarTiposEvaluacionOcupacional,
  listarTrabajadoresOcupacionalesPaginado,
  listarHistoriaOcupacionalPorOrden,
  guardarHistoriaOcupacional,
  anularHistoriaOcupacional,
  obtenerHistoriaClinicaOcupacionalConsolidada,
  obtenerResultadoClinicoOcupacional,
  guardarResultadoClinicoOcupacional,
  guardarPlantillaResultadoClinicoOcupacional,
  eliminarPlantillaResultadoClinicoOcupacional,
  actualizarDetalleOrdenOcupacional,
  listarEventosOrdenOcupacional,
  obtenerDetalleOrdenOcupacional,
  obtenerReporteOrdenesOcupacionales,
  obtenerResumenOrdenesOcupacionales,
  listarOrdenesOcupacionalesPaginado,
  previsualizarOrdenOcupacional,
  anularOrdenOcupacional,
  cerrarOrdenOcupacional,
  guardarAptitudOrdenOcupacional,
  registrarEmisionCertificadoOrdenOcupacional,
  registrarOrdenOcupacional,
} from "../../api/ocupacionalApi";
import { BASE_URL } from "../../config/config";
import { formatColegiatura, formatProfesionalName } from "../../utils/profesionalDisplay";

function resolveAssetUrl(rawValue) {
  const raw = String(rawValue || "").trim();
  if (!raw) return "";
  if (/^(https?:\/\/|data:|blob:)/i.test(raw)) return raw;
  return `${BASE_URL}${raw.replace(/^\/+/, "")}`;
}

function inferDataUrlImageFormat(dataUrl) {
  if (/^data:image\/png/i.test(String(dataUrl || ""))) return "PNG";
  if (/^data:image\/jpe?g/i.test(String(dataUrl || ""))) return "JPEG";
  return "PNG";
}

async function loadImageAsDataUrl(imageUrl) {
  if (!imageUrl) return "";
  if (/^data:image\//i.test(String(imageUrl))) {
    return String(imageUrl);
  }
  const response = await fetch(imageUrl, { credentials: "include" });
  if (!response.ok) return "";
  const blob = await response.blob();
  return await new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ""));
    reader.onerror = () => reject(new Error("No se pudo leer imagen"));
    reader.readAsDataURL(blob);
  });
}

async function fetchConfiguracionClinica() {
  const response = await fetch(`${BASE_URL}api_get_configuracion.php`, {
    method: "GET",
    credentials: "include",
  });
  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }
  if (!response.ok || !payload?.success) {
    return null;
  }
  return payload.data || null;
}

async function fetchMedicosCrud() {
  const response = await fetch(`${BASE_URL}api_medicos.php`, {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }
  if (!response.ok || !payload?.success) return [];
  return Array.isArray(payload.medicos) ? payload.medicos : [];
}

function normalizeCompareText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .toLowerCase();
}

function buildMedicoToken(medico) {
  const apellido = String(medico?.apellido || "").trim().toUpperCase();
  if (apellido) return apellido;
  const nombreCompleto = [medico?.nombre, medico?.apellido].filter(Boolean).join(" ").trim().toUpperCase();
  return nombreCompleto || "MEDICO";
}

function resolveMedicoFromOrden(det, medicos = []) {
  const lista = Array.isArray(medicos) ? medicos : [];
  if (!lista.length) return null;

  const firmas = [det?.medico_responsable, det?.firma_doctor]
    .map((v) => String(v || "").trim())
    .filter(Boolean);

  for (const raw of firmas) {
    if (/^\d+$/.test(raw)) {
      const byId = lista.find((m) => Number(m.id) === Number(raw));
      if (byId) return byId;
    }
  }

  const normalizedTokens = firmas.map((v) => normalizeCompareText(v)).filter(Boolean);
  if (!normalizedTokens.length) return null;

  const matches = lista.filter((m) => {
      const full = normalizeCompareText(`${m?.nombre || ""} ${m?.apellido || ""}`);
      const apellido = normalizeCompareText(m?.apellido || "");
      const token = normalizeCompareText(buildMedicoToken(m));
      return normalizedTokens.some((needle) => {
        if (!needle) return false;
        return (
          full === needle
          || apellido === needle
          || token === needle
          || full.includes(needle)
          || needle.includes(apellido)
        );
      });
    });

  if (!matches.length) return null;

  const withFirma = matches.find((m) => String(m?.firma || "").trim() !== "");
  return withFirma || matches[0];
}

function todayIso() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  const d = String(now.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function prettyJsonInput(value) {
  if (value == null) return "[]";
  if (typeof value === "string") {
    const trimmed = value.trim();
    return trimmed === "" ? "[]" : trimmed;
  }
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return "[]";
  }
}

export default function OrdenesOcupacionalesPage() {
  const [empresas, setEmpresas] = useState([]);
  const [empresaId, setEmpresaId] = useState(0);

  const [trabajadores, setTrabajadores] = useState([]);
  const [trabajadorId, setTrabajadorId] = useState(0);

  const [protocolos, setProtocolos] = useState([]);
  const [protocoloId, setProtocoloId] = useState(0);

  const [tipos, setTipos] = useState([]);
  const [tipoEvaluacionId, setTipoEvaluacionId] = useState(0);

  const [fechaOrden, setFechaOrden] = useState(todayIso());
  const [observacion, setObservacion] = useState("");
  const [subcontrataEmpresaId, setSubcontrataEmpresaId] = useState(0);
  const [facturarEmpresaId, setFacturarEmpresaId] = useState(0);
  const [firmaDoctor, setFirmaDoctor] = useState("GALLEGOS");
  const [medicosCrud, setMedicosCrud] = useState([]);
  const [medicoOrdenId, setMedicoOrdenId] = useState(0);
  const [modoOrden, setModoOrden] = useState("CONVALIDACION");
  const [gestanteOrden, setGestanteOrden] = useState(false);
  const [documentoOrden, setDocumentoOrden] = useState("");
  const [indicaDr, setIndicaDr] = useState("");

  const [preview, setPreview] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [registrando, setRegistrando] = useState(false);

  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState({ page: 1, per_page: 20, total: 0, total_pages: 0 });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [empresaFiltroId, setEmpresaFiltroId] = useState(0);
  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");
  const [filtroEstado, setFiltroEstado] = useState("");
  const [filtroTipo, setFiltroTipo] = useState("");
  const [filtroFechaDesde, setFiltroFechaDesde] = useState("");
  const [filtroFechaHasta, setFiltroFechaHasta] = useState("");

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [resumen, setResumen] = useState(null);
  const [exportandoGlobal, setExportandoGlobal] = useState(false);
  const [anulandoId, setAnulandoId] = useState(0);
  const [cerrandoId, setCerrandoId] = useState(0);
  const [pdfId, setPdfId] = useState(0);
  const [detalleModalOpen, setDetalleModalOpen] = useState(false);
  const [detalleModalLoading, setDetalleModalLoading] = useState(false);
  const [detalleModalData, setDetalleModalData] = useState(null);
  const [detalleModalError, setDetalleModalError] = useState("");
  const [detalleForms, setDetalleForms] = useState({});
  const [savingDetalleId, setSavingDetalleId] = useState(0);
  const [eventosFiltros, setEventosFiltros] = useState({ tipo: "", fechaDesde: "", fechaHasta: "" });
  const [eventosFiltrados, setEventosFiltrados] = useState([]);
  const [eventosLoading, setEventosLoading] = useState(false);
  const [aptitudForm, setAptitudForm] = useState({ aptitud: "", restriccion: "", recomendacion: "", medico: "" });
  const [savingAptitud, setSavingAptitud] = useState(false);
  const [certificandoId, setCertificandoId] = useState(0);
  const [historiaRows, setHistoriaRows] = useState([]);
  const [historiaLoading, setHistoriaLoading] = useState(false);
  const [historiaSaving, setHistoriaSaving] = useState(false);
  const [historiaAnulandoId, setHistoriaAnulandoId] = useState(0);
  const [historiaError, setHistoriaError] = useState("");
  const [historiaEditingId, setHistoriaEditingId] = useState(0);
  const [historiaForm, setHistoriaForm] = useState({
    motivo_evaluacion: "",
    puesto_actual: "",
    area_trabajo: "",
    tiempo_puesto_meses: "",
    observaciones: "",
    antecedentes_laborales_json: "[]",
    antecedentes_patologicos_json: "[]",
    habitos_json: "[]",
  });
  const [clinicaConsolidada, setClinicaConsolidada] = useState(null);
  const [clinicaLoading, setClinicaLoading] = useState(false);
  const [clinicaError, setClinicaError] = useState("");
  const [formatoModalOpen, setFormatoModalOpen] = useState(false);
  const [formatoModalLoading, setFormatoModalLoading] = useState(false);
  const [formatoModalSaving, setFormatoModalSaving] = useState(false);
  const [formatoModalError, setFormatoModalError] = useState("");
  const [formatoModalData, setFormatoModalData] = useState(null);
  const [formatoPlantillaSeleccionada, setFormatoPlantillaSeleccionada] = useState("0");
  const [formatoPlantillaNombre, setFormatoPlantillaNombre] = useState("");
  const [formatoPlantillaSaving, setFormatoPlantillaSaving] = useState(false);
  const [formatoForm, setFormatoForm] = useState({
    ordenDetalleId: 0,
    examenCodigo: "",
    examenDescripcion: "",
    formatoCodigo: "",
    estado: "borrador",
    observacion: "",
    datosJsonText: "{}",
  });
  const ordenesRequestRef = useRef(0);
  const resumenRequestRef = useRef(0);
  const medicoOrden = useMemo(
    () => (medicosCrud || []).find((m) => Number(m.id) === Number(medicoOrdenId)) || null,
    [medicosCrud, medicoOrdenId]
  );

  useEffect(() => {
    const t = window.setTimeout(() => {
      setQDebounced(q.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(t);
  }, [q]);

  useEffect(() => {
    let cancelled = false;

    async function boot() {
      try {
        const [empData, tipoData, medicosData] = await Promise.all([
          listarEmpresasOcupacionales({ estado: "activo" }),
          listarTiposEvaluacionOcupacional(),
          fetchMedicosCrud(),
        ]);

        if (cancelled) return;

        setEmpresas(empData || []);
        if (!empresaId && (empData || []).length > 0) {
          setEmpresaId(Number(empData[0].id));
        }

        setTipos(tipoData || []);
        if (!tipoEvaluacionId && (tipoData || []).length > 0) {
          setTipoEvaluacionId(Number(tipoData[0].id));
        }

        const medicosActivos = (medicosData || []).filter(
          (m) => String(m?.estado || "activo").toLowerCase() !== "inactivo"
        );
        setMedicosCrud(medicosActivos);
        if (!medicoOrdenId && medicosActivos.length > 0) {
          setMedicoOrdenId(Number(medicosActivos[0].id));
        }
      } catch (err) {
        if (!cancelled) {
          setError(err.message || "No se pudo cargar catalogos iniciales");
        }
      }
    }

    boot();
    return () => {
      cancelled = true;
    };
  }, [empresaId, medicoOrdenId, tipoEvaluacionId]);

  useEffect(() => {
    if (!medicoOrden) return;
    setFirmaDoctor(buildMedicoToken(medicoOrden));
  }, [medicoOrden]);

  const cargarTrabajadores = useCallback(async () => {
    if (!empresaId) {
      setTrabajadores([]);
      setTrabajadorId(0);
      return;
    }

    try {
      const payload = await listarTrabajadoresOcupacionalesPaginado({
        estado: "activo",
        empresaId,
        page: 1,
        perPage: 200,
        sortBy: "documento_numero",
        sortDir: "asc",
      });

      const list = payload.data || [];
      setTrabajadores(list);
      if (!list.find((it) => Number(it.id) === Number(trabajadorId))) {
        setTrabajadorId(list.length ? Number(list[0].id) : 0);
      }
    } catch (err) {
      setError(err.message || "No se pudo cargar trabajadores");
    }
  }, [empresaId, trabajadorId]);

  const cargarProtocolos = useCallback(async () => {
    if (!empresaId) {
      setProtocolos([]);
      setProtocoloId(0);
      return;
    }

    try {
      const data = await listarProtocolosOcupacionales({ empresaId, estado: "activo" });
      setProtocolos(data || []);
      if (!data.find((it) => Number(it.id) === Number(protocoloId))) {
        setProtocoloId(data.length ? Number(data[0].id) : 0);
      }
    } catch (err) {
      setError(err.message || "No se pudo cargar protocolos");
    }
  }, [empresaId, protocoloId]);

  useEffect(() => {
    cargarTrabajadores();
    cargarProtocolos();
  }, [cargarTrabajadores, cargarProtocolos]);

  const cargarOrdenes = useCallback(async () => {
    const requestId = ++ordenesRequestRef.current;
    setLoading(true);
    setError("");
    try {
      const payload = await listarOrdenesOcupacionalesPaginado({
        empresaId: empresaFiltroId,
        estado: filtroEstado,
        tipo: filtroTipo,
        fechaDesde: filtroFechaDesde,
        fechaHasta: filtroFechaHasta,
        q: qDebounced,
        page,
        perPage,
      });
      if (requestId !== ordenesRequestRef.current) {
        return;
      }
      setRows(payload.data || []);
      setMeta(payload.meta || { page: 1, per_page: perPage, total: 0, total_pages: 0 });
    } catch (err) {
      if (requestId !== ordenesRequestRef.current) {
        return;
      }
      setError(err.message || "No se pudo cargar ordenes");
    } finally {
      if (requestId === ordenesRequestRef.current) {
        setLoading(false);
      }
    }
  }, [empresaFiltroId, filtroEstado, filtroTipo, filtroFechaDesde, filtroFechaHasta, qDebounced, page, perPage]);

  useEffect(() => {
    cargarOrdenes();
  }, [cargarOrdenes]);

  const cargarResumen = useCallback(async () => {
    const requestId = ++resumenRequestRef.current;
    try {
      const data = await obtenerResumenOrdenesOcupacionales({
        empresaId: empresaFiltroId,
        estado: filtroEstado,
        tipo: filtroTipo,
        fechaDesde: filtroFechaDesde,
        fechaHasta: filtroFechaHasta,
        q: qDebounced,
      });
      if (requestId !== resumenRequestRef.current) {
        return;
      }
      setResumen(data);
    } catch {
      if (requestId === resumenRequestRef.current) {
        setResumen(null);
      }
    }
  }, [empresaFiltroId, filtroEstado, filtroTipo, filtroFechaDesde, filtroFechaHasta, qDebounced]);

  useEffect(() => {
    cargarResumen();
  }, [cargarResumen]);

  const recargarListadoYResumen = useCallback(async () => {
    await Promise.all([cargarOrdenes(), cargarResumen()]);
  }, [cargarOrdenes, cargarResumen]);

  const canPreview = useMemo(() => {
    return empresaId > 0 && trabajadorId > 0 && protocoloId > 0 && tipoEvaluacionId > 0;
  }, [empresaId, trabajadorId, protocoloId, tipoEvaluacionId]);

  const onPrevisualizar = async () => {
    if (!canPreview) {
      setError("Complete empresa, trabajador, protocolo y tipo de evaluacion");
      return;
    }

    setPreviewLoading(true);
    setError("");
    setMessage("");
    try {
      const data = await previsualizarOrdenOcupacional({
        empresaId,
        trabajadorId,
        protocoloId,
        tipoEvaluacionId,
      });
      setPreview(data);
    } catch (err) {
      setPreview(null);
      setError(err.message || "No se pudo previsualizar la orden");
    } finally {
      setPreviewLoading(false);
    }
  };

  const onRegistrar = async () => {
    if (!preview) {
      setError("Primero previsualice la orden");
      return;
    }

    setRegistrando(true);
    setError("");
    setMessage("");
    try {
      const firmaDoctorPayload = medicoOrden ? buildMedicoToken(medicoOrden) : String(firmaDoctor || "").trim();
      const data = await registrarOrdenOcupacional({
        empresaId,
        trabajadorId,
        protocoloId,
        tipoEvaluacionId,
        fechaOrden,
        observacion,
        subcontrataEmpresaId,
        facturarEmpresaId,
        firmaDoctor: firmaDoctorPayload,
        modo: modoOrden,
        gestante: gestanteOrden,
        documento: documentoOrden,
        indicaDr,
      });

      setMessage(`Orden registrada: ${data.codigo} (${data.total_items} examenes)`);
      setObservacion("");
      setDocumentoOrden("");
      setIndicaDr("");
      await recargarListadoYResumen();
    } catch (err) {
      setError(err.message || "No se pudo registrar la orden");
    } finally {
      setRegistrando(false);
    }
  };

  const onVerDetalle = async (ordenId) => {
    setDetalleModalOpen(true);
    setDetalleModalLoading(true);
    setDetalleModalError("");
    setDetalleModalData(null);
    setError("");
    try {
      const det = await obtenerDetalleOrdenOcupacional(ordenId);
      setDetalleModalData(det);
      const initialForms = {};
      (det.items || []).forEach((it) => {
        initialForms[it.id] = {
          estado: it.estado_ejecucion || "pendiente",
          observacion: it.observacion_ejecucion || "",
        };
      });
      setDetalleForms(initialForms);
      setEventosFiltros({ tipo: "", fechaDesde: "", fechaHasta: "" });
      setEventosFiltrados(det.eventos || []);
      setAptitudForm({
        aptitud: det.aptitud_final || "",
        restriccion: det.restriccion_final || "",
        recomendacion: det.recomendacion_final || "",
        medico: det.medico_responsable || "",
      });
      setHistoriaEditingId(0);
      setHistoriaError("");
      setHistoriaForm({
        motivo_evaluacion: "",
        puesto_actual: det.puesto_trabajo || "",
        area_trabajo: "",
        tiempo_puesto_meses: "",
        observaciones: "",
        antecedentes_laborales_json: "[]",
        antecedentes_patologicos_json: "[]",
        habitos_json: "[]",
      });
      setHistoriaLoading(true);
      try {
        const historia = await listarHistoriaOcupacionalPorOrden(ordenId);
        setHistoriaRows(historia.data || []);
      } catch (histErr) {
        setHistoriaError(histErr.message || "No se pudo cargar historia ocupacional");
        setHistoriaRows([]);
      } finally {
        setHistoriaLoading(false);
      }

      setClinicaLoading(true);
      setClinicaError("");
      try {
        const consolidada = await obtenerHistoriaClinicaOcupacionalConsolidada(ordenId);
        setClinicaConsolidada(consolidada);
      } catch (clinErr) {
        setClinicaConsolidada(null);
        setClinicaError(clinErr.message || "No se pudo cargar historia clinica consolidada");
      } finally {
        setClinicaLoading(false);
      }
    } catch (err) {
      const msg = err.message || "No se pudo obtener detalle de la orden";
      setDetalleModalError(msg);
      setError(msg);
    } finally {
      setDetalleModalLoading(false);
    }
  };

  const recargarDetalleModal = async (ordenId) => {
    const det = await obtenerDetalleOrdenOcupacional(ordenId);
    setDetalleModalData(det);
    const nextForms = {};
    (det.items || []).forEach((it) => {
      nextForms[it.id] = {
        estado: it.estado_ejecucion || "pendiente",
        observacion: it.observacion_ejecucion || "",
      };
    });
    setDetalleForms(nextForms);
    setEventosFiltrados(det.eventos || []);
    setAptitudForm({
      aptitud: det.aptitud_final || "",
      restriccion: det.restriccion_final || "",
      recomendacion: det.recomendacion_final || "",
      medico: det.medico_responsable || "",
    });
    try {
      const historia = await listarHistoriaOcupacionalPorOrden(ordenId);
      setHistoriaRows(historia.data || []);
    } catch {
      setHistoriaRows([]);
    }

    try {
      const consolidada = await obtenerHistoriaClinicaOcupacionalConsolidada(ordenId);
      setClinicaConsolidada(consolidada);
      setClinicaError("");
    } catch (clinErr) {
      setClinicaConsolidada(null);
      setClinicaError(clinErr.message || "No se pudo cargar historia clinica consolidada");
    }
  };

  const onRecargarClinicaConsolidada = async () => {
    if (!detalleModalData?.id) return;
    setClinicaLoading(true);
    setClinicaError("");
    try {
      const consolidada = await obtenerHistoriaClinicaOcupacionalConsolidada(detalleModalData.id);
      setClinicaConsolidada(consolidada);
    } catch (err) {
      setClinicaConsolidada(null);
      setClinicaError(err.message || "No se pudo recargar historia clinica consolidada");
    } finally {
      setClinicaLoading(false);
    }
  };

  const onAbrirFormatoClinico = async (item) => {
    if (!item?.id) return;
    setFormatoModalOpen(true);
    setFormatoModalLoading(true);
    setFormatoModalSaving(false);
    setFormatoModalError("");
    setFormatoModalData(null);
    setFormatoPlantillaSeleccionada("0");
    setFormatoPlantillaNombre("");
    setFormatoPlantillaSaving(false);
    setFormatoForm({
      ordenDetalleId: Number(item.id),
      examenCodigo: String(item.examen_codigo || ""),
      examenDescripcion: String(item.examen_descripcion || ""),
      formatoCodigo: String(item.examen_codigo || "formato_general").toLowerCase(),
      estado: "borrador",
      observacion: String(item.observacion_ejecucion || ""),
      datosJsonText: "{}",
    });

    try {
      const result = await obtenerResultadoClinicoOcupacional({
        ordenDetalleId: item.id,
        formatoCodigo: String(item.examen_codigo || "").toLowerCase(),
      });
      setFormatoModalData(result);
      const primerasPlantillas = Array.isArray(result?.plantillasDisponibles) ? result.plantillasDisponibles : [];
      const plantillaInicial = primerasPlantillas.length > 0 ? String(primerasPlantillas[0].id || 0) : "0";
      setFormatoPlantillaSeleccionada(plantillaInicial);
      setFormatoPlantillaNombre(`${String(item.examen_codigo || "examen").toLowerCase()}_plantilla`);

      const detalle = result?.detalle || {};
      const data = result?.data || null;
      const plantillaSugerida = result?.plantillaSugerida || {};
      const hasDataGuardada = !!(data && data.id);
      const datosJsonInicial = hasDataGuardada
        ? (data?.datos_json ?? {})
        : ((plantillaSugerida && Object.keys(plantillaSugerida).length > 0) ? plantillaSugerida : {});
      const datosJsonText = prettyJsonInput(datosJsonInicial);
      setFormatoForm({
        ordenDetalleId: Number(detalle.id || item.id),
        examenCodigo: String(detalle.examen_codigo || item.examen_codigo || ""),
        examenDescripcion: String(detalle.examen_descripcion || item.examen_descripcion || ""),
        formatoCodigo: String(data?.formato_codigo || detalle.formato_codigo || item.examen_codigo || "formato_general").toLowerCase(),
        estado: String(data?.estado || "borrador"),
        observacion: String(data?.observacion || item.observacion_ejecucion || ""),
        datosJsonText,
      });
    } catch (err) {
      setFormatoModalError(err.message || "No se pudo abrir formato clinico");
    } finally {
      setFormatoModalLoading(false);
    }
  };

  const onCargarPlantillaSugeridaFormato = () => {
    const plantilla = formatoModalData?.plantillaSugerida;
    if (!plantilla || typeof plantilla !== "object") {
      setFormatoModalError("No hay plantilla sugerida para este examen");
      return;
    }
    setFormatoForm((prev) => ({ ...prev, datosJsonText: prettyJsonInput(plantilla) }));
    setFormatoModalError("");
  };

  const onAplicarPlantillaSeleccionada = () => {
    const list = Array.isArray(formatoModalData?.plantillasDisponibles) ? formatoModalData.plantillasDisponibles : [];
    const selected = list.find((tpl) => String(tpl.id || 0) === String(formatoPlantillaSeleccionada || "0"));
    if (!selected || typeof selected.datos_json !== "object") {
      setFormatoModalError("Seleccione una plantilla valida");
      return;
    }
    setFormatoForm((prev) => ({ ...prev, datosJsonText: prettyJsonInput(selected.datos_json) }));
    setFormatoModalError("");
  };

  const onGuardarPlantillaCatalogo = async () => {
    let parsedDatos = {};
    try {
      parsedDatos = JSON.parse(String(formatoForm.datosJsonText || "{}").trim() || "{}");
      if (parsedDatos === null || typeof parsedDatos !== "object" || Array.isArray(parsedDatos)) {
        throw new Error("El JSON del formato debe ser un objeto para guardarlo como plantilla");
      }
    } catch (err) {
      setFormatoModalError(err.message || "JSON invalido para plantilla");
      return;
    }

    const nombre = String(formatoPlantillaNombre || "").trim();
    if (!nombre) {
      setFormatoModalError("Ingrese un nombre de plantilla");
      return;
    }

    setFormatoPlantillaSaving(true);
    setFormatoModalError("");
    try {
      const saved = await guardarPlantillaResultadoClinicoOcupacional({
        ordenDetalleId: Number(formatoForm.ordenDetalleId || 0),
        nombre: String(nombre).trim(),
        templateCode: formatoModalData?.detalle?.template_code || "",
        examenCodigo: formatoForm.examenCodigo || "",
        examenDescripcion: formatoForm.examenDescripcion || "",
        formatoCodigo: formatoForm.formatoCodigo || "",
        datosJson: parsedDatos,
      });

      const existentes = Array.isArray(formatoModalData?.plantillasDisponibles)
        ? formatoModalData.plantillasDisponibles.filter((tpl) => Number(tpl.id || 0) !== Number(saved?.id || 0))
        : [];
      const actualizadas = [saved, ...existentes];
      setFormatoModalData((prev) => ({
        ...(prev || {}),
        plantillasDisponibles: actualizadas,
        plantillaSugerida: saved?.datos_json || prev?.plantillaSugerida || {},
      }));
      setFormatoPlantillaSeleccionada(String(saved?.id || 0));
      if (detalleModalData?.id) {
        await recargarDetalleModal(detalleModalData.id);
      }
      setMessage("Plantilla guardada en catalogo");
    } catch (err) {
      setFormatoModalError(err.message || "No se pudo guardar plantilla");
    } finally {
      setFormatoPlantillaSaving(false);
    }
  };

  const onEliminarPlantillaCatalogo = async () => {
    const list = Array.isArray(formatoModalData?.plantillasDisponibles) ? formatoModalData.plantillasDisponibles : [];
    const selected = list.find((tpl) => String(tpl.id || 0) === String(formatoPlantillaSeleccionada || "0"));
    if (!selected || Number(selected.id || 0) <= 0) {
      setFormatoModalError("Solo puede eliminar plantillas del catalogo");
      return;
    }

    const ok = window.confirm(`Eliminar plantilla "${selected.nombre || selected.codigo || selected.id}"?`);
    if (!ok) return;

    setFormatoPlantillaSaving(true);
    setFormatoModalError("");
    try {
      await eliminarPlantillaResultadoClinicoOcupacional(selected.id, {
        ordenDetalleId: Number(formatoForm.ordenDetalleId || 0),
      });
      const restantes = list.filter((tpl) => Number(tpl.id || 0) !== Number(selected.id || 0));
      setFormatoModalData((prev) => ({ ...(prev || {}), plantillasDisponibles: restantes }));
      setFormatoPlantillaSeleccionada(restantes.length > 0 ? String(restantes[0].id || 0) : "0");
      if (detalleModalData?.id) {
        await recargarDetalleModal(detalleModalData.id);
      }
      setMessage("Plantilla eliminada del catalogo");
    } catch (err) {
      setFormatoModalError(err.message || "No se pudo eliminar plantilla");
    } finally {
      setFormatoPlantillaSaving(false);
    }
  };

  const onGuardarFormatoClinico = async () => {
    if (!formatoForm.ordenDetalleId) {
      setFormatoModalError("No hay detalle seleccionado");
      return;
    }

    let parsedDatos = {};
    try {
      parsedDatos = JSON.parse(String(formatoForm.datosJsonText || "{}").trim() || "{}");
      if (parsedDatos === null || typeof parsedDatos !== "object" || Array.isArray(parsedDatos)) {
        throw new Error("datos_json debe ser un objeto JSON");
      }
    } catch (err) {
      setFormatoModalError(err.message || "JSON invalido en datos clinicos");
      return;
    }

    setFormatoModalSaving(true);
    setFormatoModalError("");
    try {
      await guardarResultadoClinicoOcupacional({
        ordenDetalleId: formatoForm.ordenDetalleId,
        formatoCodigo: formatoForm.formatoCodigo,
        datosJson: parsedDatos,
        estado: formatoForm.estado,
        observacion: formatoForm.observacion,
      });

      if (detalleModalData?.id) {
        await recargarDetalleModal(detalleModalData.id);
      }
      setMessage(`Formato clinico guardado (${formatoForm.examenCodigo || formatoForm.formatoCodigo})`);
      setFormatoModalOpen(false);
    } catch (err) {
      setFormatoModalError(err.message || "No se pudo guardar formato clinico");
    } finally {
      setFormatoModalSaving(false);
    }
  };

  const exportHistoriaClinicaPdf = async () => {
    if (!detalleModalData?.id) return;

    let data = clinicaConsolidada;
    if (!data) {
      data = await obtenerHistoriaClinicaOcupacionalConsolidada(detalleModalData.id);
      setClinicaConsolidada(data);
    }

    const cab = data?.cabecera || {};
    const resumenClin = data?.resumen || {};
    const detallesClin = data?.detalles || [];
    const historiasClin = data?.historias || [];

    const jsPDF = (await import("jspdf")).default;
    const autoTable = (await import("jspdf-autotable")).default;
    const doc = new jsPDF();

    doc.setFontSize(14);
    doc.text("HISTORIA CLINICA OCUPACIONAL CONSOLIDADA", 105, 14, { align: "center" });
    doc.setFontSize(10);
    doc.text(`Orden: ${cab.codigo || ""} | Fecha: ${cab.fecha_orden || ""} | Estado: ${cab.estado || ""}`, 14, 22);
    doc.text(`Empresa: ${cab.empresa || ""}`, 14, 28);
    doc.text(`Documento: ${cab.documento_numero || ""} | Puesto: ${cab.puesto_trabajo || ""}`, 14, 34);
    doc.text(`Protocolo: ${cab.protocolo_descripcion || ""} | Tipo: ${cab.tipo_codigo || ""} - ${cab.tipo_nombre || ""}`, 14, 40);

    autoTable(doc, {
      startY: 46,
      head: [["Resumen clinico", "Valor"]],
      body: [
        ["Total examenes", String(resumenClin.total_items || 0)],
        ["Completados", String(resumenClin.total_completados || 0)],
        ["Observados", String(resumenClin.total_observados || 0)],
        ["Pendientes", String(resumenClin.total_pendientes || 0)],
        ["Avance", `${resumenClin.porcentaje_avance || 0}%`],
        ["Historias registradas", String(resumenClin.historias_registradas || 0)],
      ],
      styles: { fontSize: 9 },
      headStyles: { fillColor: [30, 64, 175] },
    });

    autoTable(doc, {
      startY: (doc.lastAutoTable?.finalY || 46) + 6,
      head: [["#", "Codigo", "Examen", "Estado", "Observacion", "Fecha ejecucion"]],
      body: detallesClin.map((it, idx) => [
        String(idx + 1),
        String(it.examen_codigo || ""),
        String(it.examen_descripcion || ""),
        String(it.estado_ejecucion || ""),
        String(it.observacion_ejecucion || ""),
        String(it.fecha_ejecucion || ""),
      ]),
      styles: { fontSize: 8 },
      headStyles: { fillColor: [2, 132, 199] },
    });

    const historiasBody = historiasClin.length > 0
      ? historiasClin.map((h, idx) => [
        String(idx + 1),
        String(h.motivo_evaluacion || ""),
        String(h.puesto_actual || ""),
        String(h.area_trabajo || ""),
        String(h.observaciones || ""),
      ])
      : [["-", "Sin historia registrada", "", "", ""]];

    autoTable(doc, {
      startY: (doc.lastAutoTable?.finalY || 46) + 6,
      head: [["#", "Motivo", "Puesto", "Area", "Observaciones"]],
      body: historiasBody,
      styles: { fontSize: 8 },
      headStyles: { fillColor: [15, 118, 110] },
    });

    const yFinal = (doc.lastAutoTable?.finalY || 46) + 8;
    doc.setFontSize(10);
    doc.text(`Aptitud final: ${cab.aptitud_final || "No consignada"}`, 14, yFinal);
    doc.text(`Restricciones: ${cab.restriccion_final || "Ninguna"}`, 14, yFinal + 6);
    doc.text(`Recomendaciones: ${cab.recomendacion_final || "Ninguna"}`, 14, yFinal + 12);
    doc.text(`Medico responsable: ${cab.medico_responsable || "No consignado"}`, 14, yFinal + 18);

    const safeCode = String(cab.codigo || `orden_${detalleModalData.id}`).replace(/[^A-Za-z0-9_-]/g, "_");
    doc.save(`historia_clinica_ocupacional_${safeCode}.pdf`);
    setMessage(`Historia clinica PDF generada: ${safeCode}`);
  };

  const onEditarHistoria = (row) => {
    setHistoriaEditingId(Number(row.id));
    setHistoriaError("");
    setHistoriaForm({
      motivo_evaluacion: row.motivo_evaluacion || "",
      puesto_actual: row.puesto_actual || "",
      area_trabajo: row.area_trabajo || "",
      tiempo_puesto_meses: row.tiempo_puesto_meses ?? "",
      observaciones: row.observaciones || "",
      antecedentes_laborales_json: prettyJsonInput(row.antecedentes_laborales_json),
      antecedentes_patologicos_json: prettyJsonInput(row.antecedentes_patologicos_json),
      habitos_json: prettyJsonInput(row.habitos_json),
    });
  };

  const onCancelarHistoria = () => {
    setHistoriaEditingId(0);
    setHistoriaError("");
    setHistoriaForm({
      motivo_evaluacion: "",
      puesto_actual: detalleModalData?.puesto_trabajo || "",
      area_trabajo: "",
      tiempo_puesto_meses: "",
      observaciones: "",
      antecedentes_laborales_json: "[]",
      antecedentes_patologicos_json: "[]",
      habitos_json: "[]",
    });
  };

  const onGuardarHistoria = async (e) => {
    e.preventDefault();
    if (!detalleModalData?.id) return;
    setHistoriaSaving(true);
    setHistoriaError("");
    try {
      await guardarHistoriaOcupacional({
        id: historiaEditingId || undefined,
        orden_id: detalleModalData.id,
        ...historiaForm,
      });
      await recargarDetalleModal(detalleModalData.id);
      onCancelarHistoria();
      setMessage(historiaEditingId ? "Historia ocupacional actualizada" : "Historia ocupacional registrada");
    } catch (err) {
      setHistoriaError(err.message || "No se pudo guardar historia ocupacional");
    } finally {
      setHistoriaSaving(false);
    }
  };

  const onAnularHistoria = async (row) => {
    if (!row?.id || !detalleModalData?.id) return;
    if (!window.confirm("Desea anular este registro de historia ocupacional?")) return;
    const motivo = window.prompt("Motivo de anulacion de la historia ocupacional:", row.anulado_motivo || "") || "";
    setHistoriaAnulandoId(Number(row.id));
    setHistoriaError("");
    try {
      await anularHistoriaOcupacional({ id: row.id, motivo });
      await recargarDetalleModal(detalleModalData.id);
      setMessage("Historia ocupacional anulada");
    } catch (err) {
      setHistoriaError(err.message || "No se pudo anular historia ocupacional");
    } finally {
      setHistoriaAnulandoId(0);
    }
  };

  const onGuardarAptitud = async () => {
    if (!detalleModalData?.id) return;
    if (!aptitudForm.aptitud) {
      setDetalleModalError("Seleccione aptitud final");
      return;
    }
    setSavingAptitud(true);
    setDetalleModalError("");
    setError("");
    setMessage("");
    try {
      await guardarAptitudOrdenOcupacional({
        id: detalleModalData.id,
        aptitudFinal: aptitudForm.aptitud,
        restriccionFinal: aptitudForm.restriccion,
        recomendacionFinal: aptitudForm.recomendacion,
        medicoResponsable: aptitudForm.medico,
      });
      await recargarDetalleModal(detalleModalData.id);
      await recargarListadoYResumen();
      setMessage(`Aptitud final guardada: ${aptitudForm.aptitud}`);
    } catch (err) {
      setDetalleModalError(err.message || "No se pudo guardar aptitud final");
    } finally {
      setSavingAptitud(false);
    }
  };

  const onEmitirCertificado = async (ordenId) => {
    setCertificandoId(Number(ordenId));
    setError("");
    setMessage("");
    try {
      const det = await obtenerDetalleOrdenOcupacional(ordenId);
      if (String(det.estado) !== "cerrada") {
        throw new Error("El certificado solo se emite para orden cerrada");
      }
      if (!String(det.aptitud_final || "").trim()) {
        throw new Error("Debe registrar aptitud final antes de emitir certificado");
      }

      const [jsPDFModule, autoTableModule, configuracionClinica] = await Promise.all([
        import("jspdf"),
        import("jspdf-autotable"),
        fetchConfiguracionClinica(),
      ]);
      const jsPDF = jsPDFModule.default;
      const autoTable = autoTableModule.default;
      const doc = new jsPDF({ unit: "mm", format: "a4" });

      const logoUrl = resolveAssetUrl(configuracionClinica?.logo_url || "");
      const medicoOrdenCrud = resolveMedicoFromOrden(det, medicosCrud);
      const firmaOrden = String(medicoOrdenCrud?.firma || det.firma_doctor || "").trim();
      const firmaRaw = firmaOrden;
      const firmaUrl = /^(data:|https?:\/\/|blob:|uploads\/|\/uploads\/)/i.test(firmaRaw)
        ? resolveAssetUrl(firmaRaw)
        : "";

      const [logoDataUrl, firmaDataUrl] = await Promise.all([
        logoUrl ? loadImageAsDataUrl(logoUrl).catch(() => "") : Promise.resolve(""),
        firmaUrl ? loadImageAsDataUrl(firmaUrl).catch(() => "") : Promise.resolve(""),
      ]);

      const fechaEmision = new Date();
      const fechaEmisionTexto = fechaEmision.toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });

      doc.setDrawColor(15, 23, 42);
      doc.setLineWidth(0.6);
      doc.rect(8, 8, 194, 281);

      doc.setFont("helvetica", "bold");
      doc.setFontSize(54);
      doc.setTextColor(235, 238, 242);
      doc.text("OCUPACIONAL", 105, 165, { align: "center", angle: 35 });
      doc.setTextColor(17, 24, 39);

      if (logoDataUrl) {
        const logoType = inferDataUrlImageFormat(logoDataUrl);
        doc.addImage(logoDataUrl, logoType, 12, 12, 24, 24);
      }

      doc.setFont("helvetica", "bold");
      doc.setFontSize(16);
      doc.text(String(configuracionClinica?.nombre_clinica || "CLINICA 2 DE MAYO"), 40, 19);
      doc.setFont("helvetica", "normal");
      doc.setFontSize(9.5);
      doc.text(`RUC: ${String(configuracionClinica?.ruc || "-")}`, 40, 24);
      doc.text(`Direccion: ${String(configuracionClinica?.direccion || "-")}`, 40, 28);
      doc.text(`Telefono: ${String(configuracionClinica?.telefono || "-")}`, 40, 32);

      doc.setDrawColor(148, 163, 184);
      doc.setLineWidth(0.3);
      doc.line(12, 38, 198, 38);

      doc.setFont("helvetica", "bold");
      doc.setFontSize(17);
      doc.text("CERTIFICADO DE APTITUD OCUPACIONAL", 105, 48, { align: "center" });
      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      doc.text(`Emitido: ${fechaEmisionTexto}`, 198, 54, { align: "right" });

      autoTable(doc, {
        startY: 58,
        theme: "grid",
        margin: { left: 12, right: 12 },
        tableWidth: 186,
        styles: { fontSize: 9.5, cellPadding: 2.1, textColor: [17, 24, 39], valign: "middle" },
        headStyles: { fillColor: [30, 41, 59], textColor: [255, 255, 255], fontStyle: "bold" },
        columnStyles: {
          0: { cellWidth: 34, fontStyle: "bold" },
          1: { cellWidth: 59 },
          2: { cellWidth: 29, fontStyle: "bold" },
          3: { cellWidth: 64 },
        },
        head: [["Datos paciente", "", "Orden", ""]],
        body: [[
          "Nombres y apellidos",
          String(det.paciente_nombre_completo || "-"),
          "HC",
          String(det.paciente_historia_clinica || "-"),
        ], [
          "Documento",
          String(det.documento_numero || "-"),
          "Codigo",
          String(det.codigo || "-"),
        ], [
          "Empresa",
          String(det.empresa || "-"),
          "Fecha orden",
          String(det.fecha_orden || "-"),
        ], [
          "Puesto",
          String(det.puesto_trabajo || "-"),
          "Tipo eval.",
          `${String(det.tipo_codigo || "")} ${String(det.tipo_nombre || "")}`.trim() || "-",
        ], [
          "Protocolo",
          String(det.protocolo_descripcion || "-"),
          "Estado orden",
          String(det.estado || "-"),
        ]],
      });

      const aptitudY = (doc.lastAutoTable?.finalY || 96) + 8;
      doc.setDrawColor(30, 41, 59);
      doc.setFillColor(248, 250, 252);
      doc.rect(12, aptitudY, 186, 34, "FD");

      doc.setFont("helvetica", "bold");
      doc.setFontSize(13);
      doc.text(`APTITUD FINAL: ${String(det.aptitud_final || "-").toUpperCase()}`, 16, aptitudY + 8);

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      const restricciones = doc.splitTextToSize(`Restricciones: ${String(det.restriccion_final || "Ninguna")}`, 178);
      const recomendaciones = doc.splitTextToSize(`Recomendaciones: ${String(det.recomendacion_final || "Ninguna")}`, 178);
      doc.text(restricciones, 16, aptitudY + 15);
      doc.text(recomendaciones, 16, aptitudY + 23);

      const declaracionY = aptitudY + 44;
      const declaracion =
        "El presente certificado acredita la aptitud ocupacional del trabajador en base a la evaluacion clinica y el cierre formal de la orden ocupacional.";
      doc.setFont("helvetica", "normal");
      doc.setFontSize(10.2);
      doc.text(doc.splitTextToSize(declaracion, 184), 12, declaracionY);

      const bloqueFirmaY = 246;
      doc.setDrawColor(148, 163, 184);
      doc.line(124, bloqueFirmaY, 196, bloqueFirmaY);

      if (firmaDataUrl) {
        const firmaType = inferDataUrlImageFormat(firmaDataUrl);
        doc.addImage(firmaDataUrl, firmaType, 138, 226, 44, 18);
      }

      doc.setFont("helvetica", "bold");
      doc.setFontSize(10);
      doc.text("MEDICO RESPONSABLE", 160, bloqueFirmaY + 5, { align: "center" });
      doc.setFont("helvetica", "normal");
      doc.setFontSize(9.5);
      const medicoFirmaNombre = medicoOrdenCrud
        ? formatProfesionalName(medicoOrdenCrud)
        : String(det.medico_responsable || det.firma_doctor || "No consignado");
      doc.text(medicoFirmaNombre, 160, bloqueFirmaY + 10, { align: "center" });
      if (medicoOrdenCrud) {
        doc.setFontSize(8.5);
        const especialidad = String(medicoOrdenCrud.especialidad || "").trim();
        if (especialidad) {
          doc.text(especialidad, 160, bloqueFirmaY + 14, { align: "center" });
        }
        doc.text(formatColegiatura(medicoOrdenCrud), 160, bloqueFirmaY + 18, { align: "center" });
        if (String(medicoOrdenCrud.rne || "").trim()) {
          doc.text(`RNE: ${String(medicoOrdenCrud.rne || "")}`, 160, bloqueFirmaY + 22, { align: "center" });
        }
      }

      doc.setFontSize(8.5);
      doc.setTextColor(71, 85, 105);
      doc.text(`Codigo de orden: ${String(det.codigo || "-")}`, 12, 279);
      doc.text("Documento generado por el Sistema Clinica 2 de Mayo", 198, 279, { align: "right" });
      doc.setTextColor(17, 24, 39);

      const safeCode = String(det.codigo || `orden_${ordenId}`).replace(/[^A-Za-z0-9_-]/g, "_");
      doc.save(`certificado_aptitud_${safeCode}.pdf`);
      let auditOk = true;
      try {
        await registrarEmisionCertificadoOrdenOcupacional({ id: Number(det.id || ordenId), formato: "pdf" });
      } catch {
        auditOk = false;
      }

      if (detalleModalData?.id && Number(detalleModalData.id) === Number(det.id || ordenId)) {
        await recargarDetalleModal(detalleModalData.id);
      }
      await recargarListadoYResumen();
      setMessage(auditOk ? `Certificado emitido: ${safeCode}` : `Certificado emitido: ${safeCode} (sin auditoria)`);
    } catch (err) {
      setError(err.message || "No se pudo emitir certificado");
    } finally {
      setCertificandoId(0);
    }
  };

  const onFiltrarEventos = async () => {
    if (!detalleModalData?.id) {
      return;
    }
    setEventosLoading(true);
    setDetalleModalError("");
    try {
      const evs = await listarEventosOrdenOcupacional({
        ordenId: detalleModalData.id,
        tipo: eventosFiltros.tipo,
        fechaDesde: eventosFiltros.fechaDesde,
        fechaHasta: eventosFiltros.fechaHasta,
        limit: 200,
      });
      setEventosFiltrados(evs || []);
    } catch (err) {
      setDetalleModalError(err.message || "No se pudo filtrar bitacora");
    } finally {
      setEventosLoading(false);
    }
  };

  const onResetFiltrosEventos = () => {
    setEventosFiltros({ tipo: "", fechaDesde: "", fechaHasta: "" });
    setEventosFiltrados(detalleModalData?.eventos || []);
  };

  const exportEventosPdf = async () => {
    if (!detalleModalData) {
      return;
    }
    const jsPDF = (await import("jspdf")).default;
    const autoTable = (await import("jspdf-autotable")).default;
    const doc = new jsPDF();
    doc.setFontSize(13);
    doc.text(`Bitacora de Orden ${detalleModalData.codigo}`, 14, 14);
    doc.setFontSize(10);
    doc.text(`Estado: ${detalleModalData.estado} | Avance: ${detalleModalData.total_completados || 0}/${detalleModalData.total_items || 0}`, 14, 21);
    autoTable(doc, {
      startY: 27,
      head: [["Fecha", "Tipo", "Descripcion", "Usuario"]],
      body: (eventosFiltrados || []).map((ev) => [
        String(ev.created_at || ""),
        String(ev.tipo_evento || ""),
        String(ev.descripcion || ""),
        String(ev.created_by ?? ""),
      ]),
      styles: { fontSize: 9 },
      headStyles: { fillColor: [30, 41, 59] },
    });
    const safeCode = String(detalleModalData.codigo || "orden").replace(/[^A-Za-z0-9_-]/g, "_");
    doc.save(`bitacora_${safeCode}.pdf`);
  };

  const exportEventosExcel = async () => {
    if (!detalleModalData) {
      return;
    }
    const XLSX = await import("xlsx");
    const ws = XLSX.utils.json_to_sheet((eventosFiltrados || []).map((ev) => ({
      Fecha: ev.created_at || "",
      Tipo: ev.tipo_evento || "",
      Descripcion: ev.descripcion || "",
      Usuario: ev.created_by ?? "",
      Payload: ev.payload_json || "",
    })));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Bitacora");
    const safeCode = String(detalleModalData.codigo || "orden").replace(/[^A-Za-z0-9_-]/g, "_");
    XLSX.writeFile(wb, `bitacora_${safeCode}.xlsx`);
  };

  const onGuardarDetalle = async (itemId) => {
    const form = detalleForms[itemId] || { estado: "pendiente", observacion: "" };
    if (!detalleModalData?.id) {
      return;
    }

    setSavingDetalleId(Number(itemId));
    setDetalleModalError("");
    setError("");
    setMessage("");
    try {
      await actualizarDetalleOrdenOcupacional({
        detalleId: itemId,
        estadoEjecucion: form.estado,
        observacionEjecucion: form.observacion,
      });
      await recargarDetalleModal(detalleModalData.id);
      await recargarListadoYResumen();
      setMessage(`Detalle actualizado (${itemId})`);
    } catch (err) {
      const msg = err.message || "No se pudo actualizar estado del examen";
      setDetalleModalError(msg);
      setError(msg);
    } finally {
      setSavingDetalleId(0);
    }
  };

  const onImprimir = async (ordenId) => {
    setError("");
    try {
      const det = await obtenerDetalleOrdenOcupacional(ordenId);
      const win = window.open("", "_blank", "width=900,height=700");
      if (!win) {
        setError("No se pudo abrir ventana de impresion. Verifique bloqueador de popups.");
        return;
      }

      const rowsHtml = (det.items || [])
        .map((it, idx) => `
          <tr>
            <td>${idx + 1}</td>
            <td>${it.examen_codigo}</td>
            <td>${it.examen_descripcion}</td>
            <td style="text-align:right;">S/ ${it.monto}</td>
          </tr>
        `)
        .join("");

      win.document.write(`
        <html>
          <head>
            <title>Orden ${det.codigo}</title>
            <style>
              body { font-family: Arial, sans-serif; color:#111; margin:24px; }
              h1 { margin:0 0 8px; font-size:20px; }
              .meta { font-size:12px; margin-bottom:14px; line-height:1.5; }
              table { width:100%; border-collapse:collapse; font-size:12px; }
              th, td { border:1px solid #d1d5db; padding:6px 8px; }
              th { background:#f1f5f9; text-align:left; }
              .total { margin-top:10px; text-align:right; font-size:14px; }
            </style>
          </head>
          <body>
            <h1>Hoja de Ruta de Examenes Ocupacionales</h1>
            <div class="meta">
              <div><strong>Orden:</strong> ${det.codigo} | <strong>Fecha:</strong> ${det.fecha_orden} | <strong>Estado:</strong> ${det.estado}</div>
              <div><strong>Empresa:</strong> ${det.empresa}</div>
              <div><strong>Documento:</strong> ${det.documento_numero} | <strong>Puesto:</strong> ${det.puesto_trabajo}</div>
              <div><strong>Protocolo:</strong> ${det.protocolo_descripcion} | <strong>Tipo:</strong> ${det.tipo_codigo} - ${det.tipo_nombre}</div>
            </div>
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Codigo</th>
                  <th>Examen</th>
                  <th style="text-align:right;">Monto</th>
                </tr>
              </thead>
              <tbody>${rowsHtml}</tbody>
            </table>
            <div class="total"><strong>Total: S/ ${det.monto_total}</strong></div>
          </body>
        </html>
      `);
      win.document.close();
      win.focus();
      win.print();
    } catch (err) {
      setError(err.message || "No se pudo imprimir la orden");
    }
  };

  const onDescargarPdf = async (ordenId) => {
    setPdfId(Number(ordenId));
    setError("");
    setMessage("");
    try {
      const det = await obtenerDetalleOrdenOcupacional(ordenId);
      const jsPDF = (await import("jspdf")).default;
      const autoTable = (await import("jspdf-autotable")).default;

      const doc = new jsPDF();
      doc.setFontSize(14);
      doc.text("Hoja de Ruta de Examenes Ocupacionales", 14, 14);
      doc.setFontSize(10);
      doc.text(`Orden: ${det.codigo}    Fecha: ${det.fecha_orden}    Estado: ${det.estado}`, 14, 22);
      doc.text(`Empresa: ${det.empresa}`, 14, 28);
      doc.text(`Documento: ${det.documento_numero}    Puesto: ${det.puesto_trabajo}`, 14, 34);
      doc.text(`Protocolo: ${det.protocolo_descripcion}`, 14, 40);
      doc.text(`Tipo: ${det.tipo_codigo} - ${det.tipo_nombre}`, 14, 46);

      autoTable(doc, {
        startY: 52,
        head: [["#", "Codigo", "Examen", "Monto"]],
        body: (det.items || []).map((it, idx) => [
          String(idx + 1),
          String(it.examen_codigo || ""),
          String(it.examen_descripcion || ""),
          `S/ ${it.monto || "0.00"}`,
        ]),
        styles: { fontSize: 9 },
        headStyles: { fillColor: [15, 23, 42] },
        columnStyles: { 3: { halign: "right" } },
      });

      const yFinal = (doc.lastAutoTable?.finalY || 52) + 8;
      doc.setFontSize(11);
      doc.text(`Total: S/ ${det.monto_total || "0.00"}`, 14, yFinal);

      const safeCode = String(det.codigo || `orden_${ordenId}`).replace(/[^A-Za-z0-9_-]/g, "_");
      doc.save(`orden_ocupacional_${safeCode}.pdf`);
      setMessage(`PDF generado: ${safeCode}`);
    } catch (err) {
      setError(err.message || "No se pudo generar PDF de la orden");
    } finally {
      setPdfId(0);
    }
  };

  const onAnular = async (row) => {
    if (!row || Number(row.id) <= 0) {
      return;
    }
    if (String(row.estado || "") === "anulada") {
      setError("La orden ya se encuentra anulada");
      return;
    }
    if (String(row.estado || "") === "completada") {
      setError("No se puede anular una orden completada");
      return;
    }
    if (String(row.estado || "") === "cerrada") {
      setError("No se puede anular una orden cerrada");
      return;
    }
    const motivo = (window.prompt(`Motivo de anulacion para ${row.codigo}:`) || "").trim();
    if (!motivo) {
      setError("Debe ingresar motivo de anulacion");
      return;
    }
    const ok = window.confirm(`Confirma anular la orden ${row.codigo}?`);
    if (!ok) {
      return;
    }

    setAnulandoId(Number(row.id));
    setError("");
    setMessage("");
    try {
      await anularOrdenOcupacional(row.id, motivo);
      setMessage(`Orden anulada: ${row.codigo}`);
      await recargarListadoYResumen();
    } catch (err) {
      setError(err.message || "No se pudo anular la orden");
    } finally {
      setAnulandoId(0);
    }
  };

  const onCerrarOrden = async (row) => {
    if (!row || Number(row.id) <= 0) {
      return;
    }
    if (String(row.estado || "") === "cerrada") {
      setError("La orden ya se encuentra cerrada");
      return;
    }
    if (String(row.estado || "") !== "completada") {
      setError("Solo se puede cerrar una orden en estado completada");
      return;
    }

    const ok = window.confirm(`Confirma cierre formal de la orden ${row.codigo}?`);
    if (!ok) {
      return;
    }

    setCerrandoId(Number(row.id));
    setError("");
    setMessage("");
    try {
      await cerrarOrdenOcupacional(row.id);
      setMessage(`Orden cerrada: ${row.codigo}`);
      await recargarListadoYResumen();
    } catch (err) {
      setError(err.message || "No se pudo cerrar la orden");
    } finally {
      setCerrandoId(0);
    }
  };

  const exportReporteGlobalPdf = async () => {
    setExportandoGlobal(true);
    setError("");
    try {
      const dataset = await obtenerReporteOrdenesOcupacionales({
        empresaId: empresaFiltroId,
        estado: filtroEstado,
        tipo: filtroTipo,
        fechaDesde: filtroFechaDesde,
        fechaHasta: filtroFechaHasta,
        q: qDebounced,
        limit: 5000,
      });
      const jsPDF = (await import("jspdf")).default;
      const autoTable = (await import("jspdf-autotable")).default;
      const doc = new jsPDF();
      doc.setFontSize(13);
      doc.text("Reporte Global de Ordenes Ocupacionales", 14, 14);
      doc.setFontSize(10);
      doc.text(`Total filas: ${dataset.length}`, 14, 21);
      autoTable(doc, {
        startY: 27,
        head: [["Codigo", "Fecha", "Estado", "Empresa", "Documento", "Tipo", "Avance", "Monto"]],
        body: dataset.map((r) => [
          r.codigo,
          r.fecha_orden,
          r.estado,
          r.empresa,
          r.documento_numero,
          r.tipo_codigo,
          `${r.total_completados || 0}/${r.total_items || 0}`,
          `S/ ${r.monto_total}`,
        ]),
        styles: { fontSize: 8 },
        headStyles: { fillColor: [15, 23, 42] },
      });
      doc.save(`reporte_ordenes_ocupacionales_${Date.now()}.pdf`);
      setMessage(`Reporte PDF generado (${dataset.length} filas)`);
    } catch (err) {
      setError(err.message || "No se pudo exportar reporte PDF");
    } finally {
      setExportandoGlobal(false);
    }
  };

  const exportReporteGlobalExcel = async () => {
    setExportandoGlobal(true);
    setError("");
    try {
      const dataset = await obtenerReporteOrdenesOcupacionales({
        empresaId: empresaFiltroId,
        estado: filtroEstado,
        tipo: filtroTipo,
        fechaDesde: filtroFechaDesde,
        fechaHasta: filtroFechaHasta,
        q: qDebounced,
        limit: 5000,
      });
      const XLSX = await import("xlsx");
      const ws = XLSX.utils.json_to_sheet(dataset.map((r) => ({
        Codigo: r.codigo,
        Fecha: r.fecha_orden,
        Estado: r.estado,
        Empresa: r.empresa,
        Documento: r.documento_numero,
        Puesto: r.puesto_trabajo,
        Protocolo: r.protocolo_descripcion,
        Tipo: r.tipo_codigo,
        Avance: `${r.total_completados || 0}/${r.total_items || 0}`,
        Monto: r.monto_total,
      })));
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Ordenes");
      XLSX.writeFile(wb, `reporte_ordenes_ocupacionales_${Date.now()}.xlsx`);
      setMessage(`Reporte Excel generado (${dataset.length} filas)`);
    } catch (err) {
      setError(err.message || "No se pudo exportar reporte Excel");
    } finally {
      setExportandoGlobal(false);
    }
  };

  const totalPages = Number(meta.total_pages || 0);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Salud Ocupacional - Ordenes</h1>
        <p className="text-sm text-slate-600 mt-1">
          Ejecute el protocolo sobre un trabajador y genere la orden con examenes aplicables.
        </p>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <h2 className="text-lg font-semibold text-slate-900">Nueva orden ocupacional</h2>
        <div className="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={empresaId}
            onChange={(e) => {
              setEmpresaId(Number(e.target.value));
              setPreview(null);
              setMessage("");
            }}
          >
            <option value={0}>Seleccione empresa</option>
            {empresas.map((e) => (
              <option key={e.id} value={e.id}>{e.razon_social}</option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={trabajadorId}
            onChange={(e) => {
              setTrabajadorId(Number(e.target.value));
              setPreview(null);
            }}
          >
            <option value={0}>Seleccione trabajador</option>
            {trabajadores.map((t) => (
              <option key={t.id} value={t.id}>
                {t.documento_numero} | {t.puesto_trabajo}
              </option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={protocoloId}
            onChange={(e) => {
              setProtocoloId(Number(e.target.value));
              setPreview(null);
            }}
          >
            <option value={0}>Seleccione protocolo</option>
            {protocolos.map((p) => (
              <option key={p.id} value={p.id}>{p.descripcion}</option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={tipoEvaluacionId}
            onChange={(e) => {
              setTipoEvaluacionId(Number(e.target.value));
              setPreview(null);
            }}
          >
            <option value={0}>Seleccione tipo</option>
            {tipos.map((t) => (
              <option key={t.id} value={t.id}>{t.codigo} - {t.nombre}</option>
            ))}
          </select>
        </div>

        <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
          <input
            type="date"
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={fechaOrden}
            onChange={(e) => setFechaOrden(e.target.value)}
          />
          <input
            className="md:col-span-2 rounded-md border border-slate-300 px-3 py-2 text-sm"
            placeholder="Observacion (opcional)"
            value={observacion}
            onChange={(e) => setObservacion(e.target.value)}
          />
        </div>

        <div className="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={subcontrataEmpresaId}
            onChange={(e) => setSubcontrataEmpresaId(Number(e.target.value || 0))}
          >
            <option value={0}>Subcontrata (opcional)</option>
            {empresas.map((e) => (
              <option key={`sub-${e.id}`} value={e.id}>{e.razon_social}</option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={facturarEmpresaId}
            onChange={(e) => setFacturarEmpresaId(Number(e.target.value || 0))}
          >
            <option value={0}>Facturar a (opcional)</option>
            {empresas.map((e) => (
              <option key={`fac-${e.id}`} value={e.id}>{e.razon_social}</option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={medicoOrdenId}
            onChange={(e) => setMedicoOrdenId(Number(e.target.value || 0))}
          >
            {medicosCrud.length === 0 ? <option value={0}>Sin medicos</option> : null}
            {medicosCrud.map((m) => (
              <option key={m.id} value={m.id}>
                {formatProfesionalName(m)}
              </option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={modoOrden}
            onChange={(e) => setModoOrden(e.target.value)}
          >
            <option value="CONVALIDACION">CONVALIDACION</option>
            <option value="REVALIDACION">REVALIDACION</option>
            <option value="REEVALUACION">REEVALUACION</option>
          </select>
        </div>

        <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
          <input
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            placeholder="Documento (opcional)"
            value={documentoOrden}
            onChange={(e) => setDocumentoOrden(e.target.value)}
          />
          <input
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            placeholder="Ind. Dr. (opcional)"
            value={indicaDr}
            onChange={(e) => setIndicaDr(e.target.value)}
          />
          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={gestanteOrden ? "1" : "0"}
            onChange={(e) => setGestanteOrden(e.target.value === "1")}
          >
            <option value="0">Gestante: NO</option>
            <option value="1">Gestante: SI</option>
          </select>
        </div>

        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={onPrevisualizar}
            disabled={!canPreview || previewLoading}
            className="rounded bg-cyan-600 px-3 py-2 text-xs font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
          >
            {previewLoading ? "Previsualizando..." : "Previsualizar examenes"}
          </button>
          <button
            type="button"
            onClick={onRegistrar}
            disabled={!preview || registrando}
            className="rounded bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
          >
            {registrando ? "Registrando..." : "Registrar orden"}
          </button>
        </div>

        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        {message ? <p className="text-sm text-emerald-600">{message}</p> : null}

        {preview ? (
          <div className="rounded border border-slate-200 p-3">
            <p className="text-xs text-slate-600 mb-2">
              Trabajador: <strong>{preview.trabajador?.documento_numero}</strong> | Protocolo: <strong>{preview.protocolo?.descripcion}</strong> | Tipo: <strong>{preview.tipo_evaluacion?.codigo}</strong>
            </p>
            <p className="text-xs text-slate-600 mb-2">
              Medico: <strong>{medicoOrden ? formatProfesionalName(medicoOrden) : (firmaDoctor || "-")}</strong> | Modo: <strong>{modoOrden || "-"}</strong> | Gestante: <strong>{gestanteOrden ? "SI" : "NO"}</strong>
            </p>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-slate-500">
                    <th className="py-2 pr-3">Codigo</th>
                    <th className="py-2 pr-3">Examen</th>
                    <th className="py-2 pr-3">Aplica</th>
                    <th className="py-2 pr-3">Motivo</th>
                    <th className="py-2 pr-3">Monto</th>
                  </tr>
                </thead>
                <tbody>
                  {(preview.items || []).map((it) => (
                    <tr key={`${it.catalogo_id}-${it.examen_id}`} className="border-b last:border-0">
                      <td className="py-2 pr-3">{it.codigo}</td>
                      <td className="py-2 pr-3">{it.descripcion}</td>
                      <td className="py-2 pr-3">
                        <span className={`rounded-full px-2 py-1 text-xs ${it.aplica ? "bg-emerald-100 text-emerald-700" : "bg-slate-200 text-slate-700"}`}>
                          {it.aplica ? "SI" : "NO"}
                        </span>
                      </td>
                      <td className="py-2 pr-3">{it.motivo}</td>
                      <td className="py-2 pr-3">S/ {it.aplica ? it.monto : "0.00"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-2 text-sm text-slate-700">
              Total aplicable: <strong>S/ {preview.total || "0.00"}</strong> | Examenes aplicables: <strong>{preview.total_items_aplican || 0}</strong>
            </p>
          </div>
        ) : null}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-semibold text-slate-900">Ordenes registradas</h2>
          <input
            className="ml-auto rounded-md border border-slate-300 px-3 py-2 text-sm"
            placeholder="Buscar por codigo, documento o protocolo"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={perPage}
            onChange={(e) => {
              setPerPage(Number(e.target.value));
              setPage(1);
            }}
          >
            <option value={10}>10</option>
            <option value={20}>20</option>
            <option value={50}>50</option>
          </select>
        </div>

        <div className="mb-3 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-7">
          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={empresaFiltroId}
            onChange={(e) => {
              setEmpresaFiltroId(Number(e.target.value));
              setPage(1);
            }}
          >
            <option value={0}>Todas las empresas</option>
            {empresas.map((empresa) => (
              <option key={empresa.id} value={empresa.id}>{empresa.razon_social}</option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={filtroEstado}
            onChange={(e) => {
              setFiltroEstado(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos los estados</option>
            <option value="emitida">emitida</option>
            <option value="en_proceso">en_proceso</option>
            <option value="completada">completada</option>
            <option value="cerrada">cerrada</option>
            <option value="anulada">anulada</option>
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={filtroTipo}
            onChange={(e) => {
              setFiltroTipo(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos los tipos</option>
            {tipos.map((t) => (
              <option key={t.id} value={t.codigo}>{t.codigo}</option>
            ))}
          </select>

          <input
            type="date"
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={filtroFechaDesde}
            onChange={(e) => {
              setFiltroFechaDesde(e.target.value);
              setPage(1);
            }}
          />

          <input
            type="date"
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={filtroFechaHasta}
            onChange={(e) => {
              setFiltroFechaHasta(e.target.value);
              setPage(1);
            }}
          />

          <button
            type="button"
            className="rounded border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
            onClick={exportReporteGlobalPdf}
            disabled={exportandoGlobal}
          >
            {exportandoGlobal ? "Exportando..." : "Reporte PDF"}
          </button>

          <button
            type="button"
            className="rounded border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
            onClick={exportReporteGlobalExcel}
            disabled={exportandoGlobal}
          >
            {exportandoGlobal ? "Exportando..." : "Reporte Excel"}
          </button>
        </div>

        <div className="mb-3 grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-7">
          <div className="rounded border border-slate-200 bg-slate-50 p-2 text-xs">Total: <strong>{resumen?.total || 0}</strong></div>
          <div className="rounded border border-emerald-200 bg-emerald-50 p-2 text-xs">Emitida: <strong>{resumen?.emitida || 0}</strong></div>
          <div className="rounded border border-amber-200 bg-amber-50 p-2 text-xs">En proceso: <strong>{resumen?.en_proceso || 0}</strong></div>
          <div className="rounded border border-blue-200 bg-blue-50 p-2 text-xs">Completada: <strong>{resumen?.completada || 0}</strong></div>
          <div className="rounded border border-slate-300 bg-slate-100 p-2 text-xs">Cerrada: <strong>{resumen?.cerrada || 0}</strong></div>
          <div className="rounded border border-red-200 bg-red-50 p-2 text-xs">Anulada: <strong>{resumen?.anulada || 0}</strong></div>
          <div className="rounded border border-cyan-200 bg-cyan-50 p-2 text-xs">Monto total: <strong>S/ {resumen?.monto_total || "0.00"}</strong></div>
        </div>

        {loading ? <p className="text-sm text-slate-500">Cargando ordenes...</p> : null}

        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2 pr-3">Codigo</th>
                <th className="py-2 pr-3">Fecha</th>
                <th className="py-2 pr-3">Estado</th>
                <th className="py-2 pr-3">Ejecucion</th>
                <th className="py-2 pr-3">Empresa</th>
                <th className="py-2 pr-3">Documento</th>
                <th className="py-2 pr-3">Puesto</th>
                <th className="py-2 pr-3">Protocolo</th>
                <th className="py-2 pr-3">Tipo</th>
                <th className="py-2 pr-3">Monto</th>
                <th className="py-2 pr-3">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b last:border-0">
                  <td className="py-2 pr-3">{r.codigo}</td>
                  <td className="py-2 pr-3">{r.fecha_orden}</td>
                  <td className="py-2 pr-3">
                    <span className={`rounded-full px-2 py-1 text-xs ${r.estado === "anulada" ? "bg-red-100 text-red-700" : r.estado === "cerrada" ? "bg-slate-200 text-slate-700" : r.estado === "completada" ? "bg-blue-100 text-blue-700" : r.estado === "en_proceso" ? "bg-amber-100 text-amber-700" : "bg-emerald-100 text-emerald-700"}`}>
                      {r.estado}
                    </span>
                  </td>
                  <td className="py-2 pr-3">{Number(r.total_completados || 0)}/{Number(r.total_items || 0)}</td>
                  <td className="py-2 pr-3">{r.empresa}</td>
                  <td className="py-2 pr-3">{r.documento_numero}</td>
                  <td className="py-2 pr-3">{r.puesto_trabajo}</td>
                  <td className="py-2 pr-3">{r.protocolo_descripcion}</td>
                  <td className="py-2 pr-3">{r.tipo_codigo}</td>
                  <td className="py-2 pr-3">S/ {r.monto_total}</td>
                  <td className="py-2 pr-3">
                    <div className="flex flex-wrap gap-1">
                      <button
                        type="button"
                        className="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100"
                        onClick={() => onVerDetalle(r.id)}
                      >
                        Detalle
                      </button>
                      <button
                        type="button"
                        className="rounded border border-cyan-300 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50"
                        onClick={() => onImprimir(r.id)}
                      >
                        Imprimir
                      </button>
                      <button
                        type="button"
                        className="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                        onClick={() => onDescargarPdf(r.id)}
                        disabled={pdfId === r.id}
                        title="Descargar hoja de ruta de examenes"
                      >
                        {pdfId === r.id ? "Ruta..." : "Hoja ruta"}
                      </button>
                      <button
                        type="button"
                        className="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        onClick={() => onEmitirCertificado(r.id)}
                        disabled={certificandoId === r.id || r.estado !== "cerrada" || !String(r.aptitud_final || "").trim()}
                      >
                        {certificandoId === r.id ? "Cert..." : "Certificado"}
                      </button>
                      {r.certificado_emitido ? (
                        <span className="rounded border border-emerald-200 bg-emerald-50 px-1.5 py-1 text-[10px] font-semibold text-emerald-700" title={r.certificado_emitido_at || "Certificado emitido"}>
                          ✓ Emit.
                        </span>
                      ) : null}
                      <button
                        type="button"
                        className="rounded border border-violet-300 px-2 py-1 text-xs text-violet-700 hover:bg-violet-50 disabled:opacity-50"
                        onClick={() => onCerrarOrden(r)}
                        disabled={cerrandoId === r.id || r.estado !== "completada"}
                      >
                        {cerrandoId === r.id ? "Cerrando..." : "Cerrar"}
                      </button>
                      <button
                        type="button"
                        className="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 disabled:opacity-50"
                        onClick={() => onAnular(r)}
                        disabled={anulandoId === r.id || r.estado === "anulada" || r.estado === "completada" || r.estado === "cerrada"}
                      >
                        {anulandoId === r.id ? "Anulando..." : "Anular"}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {!loading && rows.length === 0 ? (
                <tr>
                  <td className="py-3 text-slate-500" colSpan={11}>No hay ordenes para mostrar.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <div className="mt-3 flex items-center justify-between gap-2">
          <span className="text-xs text-slate-500">Total: {meta.total || 0} registros</span>
          <div className="flex items-center gap-2">
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-1 text-xs disabled:opacity-50"
              disabled={loading || page <= 1}
              onClick={() => setPage((prev) => Math.max(1, prev - 1))}
            >
              Anterior
            </button>
            <span className="text-xs text-slate-600">Pag. {page}/{Math.max(1, totalPages)}</span>
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-1 text-xs disabled:opacity-50"
              disabled={loading || page >= totalPages}
              onClick={() => setPage((prev) => prev + 1)}
            >
              Siguiente
            </button>
          </div>
        </div>
      </div>

      {detalleModalOpen ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-3">
          <div className="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h3 className="text-lg font-semibold text-slate-900">Ejecucion de orden</h3>
              <button
                type="button"
                className="rounded border border-slate-300 px-3 py-1 text-xs"
                onClick={() => setDetalleModalOpen(false)}
              >
                Cerrar
              </button>
            </div>

            {detalleModalLoading ? <p className="text-sm text-slate-500">Cargando detalle...</p> : null}
            {detalleModalError ? <p className="text-sm text-red-600">{detalleModalError}</p> : null}

            {detalleModalData ? (
              <>
                <p className="mb-3 text-sm text-slate-700">
                  Orden: <strong>{detalleModalData.codigo}</strong> | Estado: <strong>{detalleModalData.estado}</strong> | Avance: <strong>{detalleModalData.total_completados || 0}/{detalleModalData.total_items || 0}</strong>
                </p>
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="border-b text-left text-slate-500">
                        <th className="py-2 pr-3">Codigo</th>
                        <th className="py-2 pr-3">Examen</th>
                        <th className="py-2 pr-3">Estado</th>
                        <th className="py-2 pr-3">Observacion</th>
                        <th className="py-2 pr-3">F. ejecucion</th>
                        <th className="py-2 pr-3">Accion</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(detalleModalData.items || []).map((it) => (
                        <tr key={it.id} className="border-b last:border-0 align-top">
                          <td className="py-2 pr-3">{it.examen_codigo}</td>
                          <td className="py-2 pr-3">{it.examen_descripcion}</td>
                          <td className="py-2 pr-3">
                            <select
                              className="rounded border border-slate-300 px-2 py-1 text-xs"
                              value={detalleForms[it.id]?.estado || "pendiente"}
                              onChange={(e) => setDetalleForms((prev) => ({
                                ...prev,
                                [it.id]: {
                                  ...(prev[it.id] || {}),
                                  estado: e.target.value,
                                },
                              }))}
                              disabled={detalleModalData.estado === "anulada" || detalleModalData.estado === "cerrada"}
                            >
                              <option value="pendiente">pendiente</option>
                              <option value="en_proceso">en_proceso</option>
                              <option value="realizado">realizado</option>
                              <option value="observado">observado</option>
                            </select>
                          </td>
                          <td className="py-2 pr-3">
                            <input
                              className="w-56 rounded border border-slate-300 px-2 py-1 text-xs"
                              value={detalleForms[it.id]?.observacion || ""}
                              onChange={(e) => setDetalleForms((prev) => ({
                                ...prev,
                                [it.id]: {
                                  ...(prev[it.id] || {}),
                                  observacion: e.target.value,
                                },
                              }))}
                              placeholder="Observacion"
                              disabled={detalleModalData.estado === "anulada" || detalleModalData.estado === "cerrada"}
                            />
                          </td>
                          <td className="py-2 pr-3 text-xs text-slate-600">{it.fecha_ejecucion || "-"}</td>
                          <td className="py-2 pr-3">
                            <div className="flex flex-wrap gap-1">
                              <button
                                type="button"
                                className="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                                onClick={() => onAbrirFormatoClinico(it)}
                                disabled={detalleModalData.estado === "anulada" || detalleModalData.estado === "cerrada"}
                              >
                                Formato
                              </button>
                              <button
                                type="button"
                                className="rounded border border-cyan-300 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                                onClick={() => onGuardarDetalle(it.id)}
                                disabled={savingDetalleId === it.id || detalleModalData.estado === "anulada" || detalleModalData.estado === "cerrada"}
                              >
                                {savingDetalleId === it.id ? "Guardando..." : "Guardar"}
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="mt-4 rounded border border-emerald-200 bg-emerald-50/40 p-3">
                  <h4 className="mb-2 text-sm font-semibold text-emerald-900">Aptitud final y certificado</h4>
                  <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <div>
                      <label className="mb-1 block text-xs text-slate-600">Aptitud final</label>
                      <select
                        className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
                        value={aptitudForm.aptitud}
                        onChange={(e) => setAptitudForm((prev) => ({ ...prev, aptitud: e.target.value }))}
                        disabled={detalleModalData.estado !== "cerrada" || savingAptitud}
                      >
                        <option value="">Seleccione...</option>
                        <option value="APTO">APTO</option>
                        <option value="APTO_CON_RESTRICCIONES">APTO_CON_RESTRICCIONES</option>
                        <option value="NO_APTO">NO_APTO</option>
                      </select>
                    </div>
                    <div>
                      <label className="mb-1 block text-xs text-slate-600">Medico responsable</label>
                      <input
                        className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
                        value={aptitudForm.medico}
                        onChange={(e) => setAptitudForm((prev) => ({ ...prev, medico: e.target.value }))}
                        placeholder="Nombre medico"
                        disabled={detalleModalData.estado !== "cerrada" || savingAptitud}
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs text-slate-600">Restricciones</label>
                      <input
                        className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
                        value={aptitudForm.restriccion}
                        onChange={(e) => setAptitudForm((prev) => ({ ...prev, restriccion: e.target.value }))}
                        placeholder="Restricciones"
                        disabled={detalleModalData.estado !== "cerrada" || savingAptitud}
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs text-slate-600">Recomendaciones</label>
                      <input
                        className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
                        value={aptitudForm.recomendacion}
                        onChange={(e) => setAptitudForm((prev) => ({ ...prev, recomendacion: e.target.value }))}
                        placeholder="Recomendaciones"
                        disabled={detalleModalData.estado !== "cerrada" || savingAptitud}
                      />
                    </div>
                  </div>
                  <div className="mt-3 flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                      onClick={onGuardarAptitud}
                      disabled={detalleModalData.estado !== "cerrada" || savingAptitud}
                    >
                      {savingAptitud ? "Guardando..." : "Guardar aptitud"}
                    </button>
                    <button
                      type="button"
                      className="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                      onClick={() => onEmitirCertificado(detalleModalData.id)}
                      disabled={certificandoId === detalleModalData.id || detalleModalData.estado !== "cerrada" || !String(detalleModalData.aptitud_final || aptitudForm.aptitud || "").trim()}
                    >
                      {certificandoId === detalleModalData.id ? "Emitiendo..." : "Emitir certificado"}
                    </button>
                  </div>
                </div>

                <div className="mt-4 rounded border border-cyan-200 bg-cyan-50/40 p-3">
                  <h4 className="mb-2 text-sm font-semibold text-cyan-900">Historia ocupacional</h4>
                  <p className="mb-3 text-xs text-cyan-800">
                    Gestion de historia ocupacional por orden, manteniendo la logica legacy mientras la orden siga editable.
                  </p>

                  <form onSubmit={onGuardarHistoria} className="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <input
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      placeholder="Motivo de evaluacion"
                      value={historiaForm.motivo_evaluacion}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, motivo_evaluacion: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <input
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      placeholder="Puesto actual"
                      value={historiaForm.puesto_actual}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, puesto_actual: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <input
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      placeholder="Area de trabajo"
                      value={historiaForm.area_trabajo}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, area_trabajo: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <input
                      type="number"
                      min={0}
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      placeholder="Tiempo en puesto (meses)"
                      value={historiaForm.tiempo_puesto_meses}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, tiempo_puesto_meses: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <textarea
                      className="rounded border border-slate-300 px-2 py-1 text-xs md:col-span-2"
                      rows={2}
                      placeholder="Observaciones"
                      value={historiaForm.observaciones}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, observaciones: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <textarea
                      className="rounded border border-slate-300 px-2 py-1 font-mono text-xs"
                      rows={4}
                      placeholder="Antecedentes laborales JSON"
                      value={historiaForm.antecedentes_laborales_json}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, antecedentes_laborales_json: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <textarea
                      className="rounded border border-slate-300 px-2 py-1 font-mono text-xs"
                      rows={4}
                      placeholder="Antecedentes patologicos JSON"
                      value={historiaForm.antecedentes_patologicos_json}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, antecedentes_patologicos_json: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <textarea
                      className="rounded border border-slate-300 px-2 py-1 font-mono text-xs md:col-span-2"
                      rows={4}
                      placeholder="Habitos JSON"
                      value={historiaForm.habitos_json}
                      onChange={(e) => setHistoriaForm((prev) => ({ ...prev, habitos_json: e.target.value }))}
                      disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                    />
                    <div className="md:col-span-2 flex flex-wrap gap-2">
                      <button
                        type="submit"
                        className="rounded border border-cyan-300 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                        disabled={historiaSaving || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                      >
                        {historiaSaving ? "Guardando..." : historiaEditingId ? "Actualizar historia" : "Registrar historia"}
                      </button>
                      {historiaEditingId ? (
                        <button
                          type="button"
                          className="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100"
                          onClick={onCancelarHistoria}
                        >
                          Cancelar edicion
                        </button>
                      ) : null}
                    </div>
                  </form>

                  {historiaLoading ? <p className="mt-3 text-xs text-slate-500">Cargando historia ocupacional...</p> : null}
                  {historiaError ? <p className="mt-3 text-xs text-red-600">{historiaError}</p> : null}

                  <div className="mt-3 max-h-64 space-y-2 overflow-y-auto">
                    {historiaRows.length === 0 && !historiaLoading ? (
                      <p className="text-xs text-slate-500">No hay historia ocupacional registrada para esta orden.</p>
                    ) : null}
                    {historiaRows.map((row) => (
                      <div key={row.id} className="rounded border border-cyan-100 bg-white p-2 text-xs">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <p className="font-semibold text-slate-700">Registro #{row.id} - {row.created_at || ""}</p>
                          <div className="flex gap-2">
                            <button
                              type="button"
                              className="rounded border border-blue-300 px-2 py-1 text-[11px] text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                              onClick={() => onEditarHistoria(row)}
                              disabled={detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                            >
                              Editar
                            </button>
                            <button
                              type="button"
                              className="rounded border border-red-300 px-2 py-1 text-[11px] text-red-700 hover:bg-red-50 disabled:opacity-50"
                              onClick={() => onAnularHistoria(row)}
                              disabled={historiaAnulandoId === row.id || detalleModalData.estado === "cerrada" || detalleModalData.estado === "anulada"}
                            >
                              {historiaAnulandoId === row.id ? "Anulando..." : "Anular"}
                            </button>
                          </div>
                        </div>
                        <p className="mt-1 text-slate-600"><strong>Motivo:</strong> {row.motivo_evaluacion || "-"}</p>
                        <p className="text-slate-600"><strong>Puesto:</strong> {row.puesto_actual || "-"} | <strong>Area:</strong> {row.area_trabajo || "-"} | <strong>Meses:</strong> {row.tiempo_puesto_meses ?? "-"}</p>
                        <p className="text-slate-600"><strong>Observaciones:</strong> {row.observaciones || "-"}</p>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-4 rounded border border-indigo-200 bg-indigo-50/40 p-3">
                  <h4 className="mb-2 text-sm font-semibold text-indigo-900">Historia clinica ocupacional consolidada</h4>
                  <p className="mb-3 text-xs text-indigo-800">
                    Vista clinica resumida de la orden con avance de examenes, hallazgos operativos e historia ocupacional registrada.
                  </p>

                  <div className="mb-3 flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                      onClick={onRecargarClinicaConsolidada}
                      disabled={clinicaLoading}
                    >
                      {clinicaLoading ? "Recargando..." : "Recargar consolidado"}
                    </button>
                    <button
                      type="button"
                      className="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                      onClick={exportHistoriaClinicaPdf}
                      disabled={clinicaLoading || !detalleModalData?.id}
                    >
                      PDF clinico
                    </button>
                  </div>

                  {clinicaLoading ? <p className="text-xs text-slate-500">Cargando consolidado clinico...</p> : null}
                  {clinicaError ? <p className="text-xs text-red-600">{clinicaError}</p> : null}

                  {clinicaConsolidada?.resumen ? (
                    <div className="grid grid-cols-2 gap-2 md:grid-cols-6">
                      <div className="rounded border border-slate-200 bg-white p-2 text-xs">Total: <strong>{clinicaConsolidada.resumen.total_items || 0}</strong></div>
                      <div className="rounded border border-emerald-200 bg-emerald-50 p-2 text-xs">Completados: <strong>{clinicaConsolidada.resumen.total_completados || 0}</strong></div>
                      <div className="rounded border border-amber-200 bg-amber-50 p-2 text-xs">Observados: <strong>{clinicaConsolidada.resumen.total_observados || 0}</strong></div>
                      <div className="rounded border border-slate-200 bg-slate-50 p-2 text-xs">Pendientes: <strong>{clinicaConsolidada.resumen.total_pendientes || 0}</strong></div>
                      <div className="rounded border border-cyan-200 bg-cyan-50 p-2 text-xs">Avance: <strong>{clinicaConsolidada.resumen.porcentaje_avance || 0}%</strong></div>
                      <div className="rounded border border-violet-200 bg-violet-50 p-2 text-xs">Historias: <strong>{clinicaConsolidada.resumen.historias_registradas || 0}</strong></div>
                    </div>
                  ) : null}
                </div>

                <div className="mt-4 rounded border border-slate-200 p-3">
                  <h4 className="mb-2 text-sm font-semibold text-slate-800">Bitacora de eventos</h4>
                  <div className="mb-3 flex flex-wrap items-center gap-2">
                    <select
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      value={eventosFiltros.tipo}
                      onChange={(e) => setEventosFiltros((prev) => ({ ...prev, tipo: e.target.value }))}
                    >
                      <option value="">Todos los tipos</option>
                      <option value="orden_registrada">orden_registrada</option>
                      <option value="detalle_actualizado">detalle_actualizado</option>
                      <option value="orden_cerrada">orden_cerrada</option>
                      <option value="orden_anulada">orden_anulada</option>
                      <option value="aptitud_final_guardada">aptitud_final_guardada</option>
                      <option value="certificado_emitido">certificado_emitido</option>
                      <option value="plantilla_guardada">plantilla_guardada</option>
                      <option value="plantilla_eliminada">plantilla_eliminada</option>
                    </select>
                    <input
                      type="date"
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      value={eventosFiltros.fechaDesde}
                      onChange={(e) => setEventosFiltros((prev) => ({ ...prev, fechaDesde: e.target.value }))}
                    />
                    <input
                      type="date"
                      className="rounded border border-slate-300 px-2 py-1 text-xs"
                      value={eventosFiltros.fechaHasta}
                      onChange={(e) => setEventosFiltros((prev) => ({ ...prev, fechaHasta: e.target.value }))}
                    />
                    <button
                      type="button"
                      className="rounded border border-cyan-300 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                      onClick={onFiltrarEventos}
                      disabled={eventosLoading}
                    >
                      {eventosLoading ? "Filtrando..." : "Filtrar"}
                    </button>
                    <button
                      type="button"
                      className="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100"
                      onClick={onResetFiltrosEventos}
                    >
                      Limpiar
                    </button>
                    <button
                      type="button"
                      className="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50"
                      onClick={exportEventosPdf}
                    >
                      PDF
                    </button>
                    <button
                      type="button"
                      className="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                      onClick={exportEventosExcel}
                    >
                      Excel
                    </button>
                  </div>
                  <div className="max-h-48 space-y-2 overflow-y-auto">
                    {(eventosFiltrados || []).length === 0 ? (
                      <p className="text-xs text-slate-500">Sin eventos registrados.</p>
                    ) : (
                      (eventosFiltrados || []).map((ev) => (
                        <div key={ev.id} className="rounded border border-slate-100 bg-slate-50 p-2 text-xs">
                          <p className="font-medium text-slate-700">{ev.tipo_evento} - {ev.created_at || ""}</p>
                          <p className="text-slate-600">{ev.descripcion}</p>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              </>
            ) : null}
          </div>
        </div>
      ) : null}

      {formatoModalOpen ? (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-3">
          <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h3 className="text-base font-semibold text-slate-900">Formato clinico por examen</h3>
              <button
                type="button"
                className="rounded border border-slate-300 px-3 py-1 text-xs"
                onClick={() => setFormatoModalOpen(false)}
              >
                Cerrar
              </button>
            </div>

            {formatoModalLoading ? <p className="text-sm text-slate-500">Cargando formato...</p> : null}
            {formatoModalError ? <p className="text-sm text-red-600">{formatoModalError}</p> : null}

            {!formatoModalLoading ? (
              <div className="space-y-3">
                <p className="text-xs text-slate-600">
                  Examen: <strong>{formatoForm.examenCodigo}</strong> - {formatoForm.examenDescripcion}
                </p>

                <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                  <input
                    className="rounded border border-slate-300 px-2 py-1 text-xs"
                    value={formatoForm.formatoCodigo}
                    onChange={(e) => setFormatoForm((prev) => ({ ...prev, formatoCodigo: e.target.value }))}
                    placeholder="Codigo formato"
                    disabled={formatoModalSaving}
                  />
                  <select
                    className="rounded border border-slate-300 px-2 py-1 text-xs"
                    value={formatoForm.estado}
                    onChange={(e) => setFormatoForm((prev) => ({ ...prev, estado: e.target.value }))}
                    disabled={formatoModalSaving}
                  >
                    <option value="borrador">borrador</option>
                    <option value="finalizado">finalizado</option>
                    <option value="anulado">anulado</option>
                  </select>
                  <input
                    className="rounded border border-slate-300 px-2 py-1 text-xs"
                    value={formatoForm.observacion}
                    onChange={(e) => setFormatoForm((prev) => ({ ...prev, observacion: e.target.value }))}
                    placeholder="Observacion clinica"
                    disabled={formatoModalSaving}
                  />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    className="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                    onClick={onCargarPlantillaSugeridaFormato}
                    disabled={formatoModalSaving || formatoModalLoading || formatoPlantillaSaving}
                  >
                    Cargar plantilla sugerida
                  </button>
                  <p className="text-[11px] text-slate-500">Puede ajustar el JSON sugerido antes de guardar.</p>
                </div>

                <div className="grid grid-cols-1 gap-2 md:grid-cols-4">
                  <input
                    className="rounded border border-slate-300 px-2 py-1 text-xs md:col-span-2"
                    value={formatoPlantillaNombre}
                    onChange={(e) => setFormatoPlantillaNombre(e.target.value)}
                    placeholder="Nombre de plantilla"
                    disabled={formatoModalSaving || formatoModalLoading || formatoPlantillaSaving}
                  />
                  <select
                    className="rounded border border-slate-300 px-2 py-1 text-xs md:col-span-2"
                    value={formatoPlantillaSeleccionada}
                    onChange={(e) => setFormatoPlantillaSeleccionada(e.target.value)}
                    disabled={formatoModalSaving || formatoModalLoading || formatoPlantillaSaving}
                  >
                    {(formatoModalData?.plantillasDisponibles || []).map((tpl) => (
                      <option key={`${tpl.id || 0}-${tpl.codigo || "tpl"}`} value={String(tpl.id || 0)}>
                        {tpl.nombre || tpl.codigo || `Plantilla ${tpl.id || 0}`}
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    className="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                    onClick={onAplicarPlantillaSeleccionada}
                    disabled={formatoModalSaving || formatoModalLoading || formatoPlantillaSaving}
                  >
                    Aplicar seleccionada
                  </button>
                  <button
                    type="button"
                    className="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 disabled:opacity-50"
                    onClick={onGuardarPlantillaCatalogo}
                    disabled={formatoModalSaving || formatoModalLoading || formatoPlantillaSaving}
                  >
                    {formatoPlantillaSaving ? "Guardando..." : "Guardar como plantilla"}
                  </button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    className="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 disabled:opacity-50"
                    onClick={onEliminarPlantillaCatalogo}
                    disabled={formatoModalSaving || formatoModalLoading || formatoPlantillaSaving || Number(formatoPlantillaSeleccionada || 0) <= 0}
                  >
                    Eliminar plantilla seleccionada
                  </button>
                  <p className="text-[11px] text-slate-500">Solo se eliminan plantillas guardadas en catalogo (no la sugerida del sistema).</p>
                </div>

                <textarea
                  className="w-full rounded border border-slate-300 px-2 py-1 font-mono text-xs"
                  rows={16}
                  value={formatoForm.datosJsonText}
                  onChange={(e) => setFormatoForm((prev) => ({ ...prev, datosJsonText: e.target.value }))}
                  placeholder='{"hallazgos":"", "conclusion":""}'
                  disabled={formatoModalSaving}
                />

                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="rounded border border-cyan-300 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                    onClick={onGuardarFormatoClinico}
                    disabled={formatoModalSaving}
                  >
                    {formatoModalSaving ? "Guardando..." : "Guardar formato"}
                  </button>
                </div>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
