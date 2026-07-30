import { useCallback, useEffect, useMemo, useState } from "react";
import {
  FiAlertCircle,
  FiArrowLeft,
  FiCheckCircle,
  FiChevronLeft,
  FiChevronRight,
  FiClock,
  FiFileText,
  FiPrinter,
  FiSave,
  FiTrash2,
  FiUser,
} from "react-icons/fi";
import { Link, useLocation, useParams } from "react-router-dom";
import {
  eliminarPlantillaResultadoClinicoOcupacional,
  guardarPlantillaResultadoClinicoOcupacional,
  guardarResultadoClinicoOcupacional,
  obtenerDetalleOrdenOcupacional,
  obtenerResultadoClinicoOcupacional,
  registrarEmisionPdfResultadoClinicoOcupacional,
} from "../../api/ocupacionalApi";
import FormatoClinicoCampos from "../../components/ocupacional/FormatoClinicoCampos";
import { BASE_URL } from "../../config/config";
import { authFetch } from "../../utils/apiClient";
import { formatColegiatura, formatProfesionalName } from "../../utils/profesionalDisplay";

const RESULTADO_ESTADOS = {
  pendiente: { label: "Sin atender", icon: FiAlertCircle, className: "border-red-200 bg-red-50 text-red-700" },
  borrador: { label: "En proceso", icon: FiClock, className: "border-amber-200 bg-amber-50 text-amber-800" },
  finalizado: { label: "Finalizado", icon: FiCheckCircle, className: "border-emerald-200 bg-emerald-50 text-emerald-700" },
  observado: { label: "Observado", icon: FiAlertCircle, className: "border-rose-300 bg-rose-100 text-rose-800" },
};

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
  if (/^data:image\//i.test(String(imageUrl))) return String(imageUrl);
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

async function cropImageWhitespaceDataUrl(dataUrl) {
  if (!dataUrl) return "";
  const image = await new Promise((resolve, reject) => {
    const loadedImage = new Image();
    loadedImage.onload = () => resolve(loadedImage);
    loadedImage.onerror = () => reject(new Error("No se pudo procesar el logo ocupacional"));
    loadedImage.src = dataUrl;
  });
  const sourceCanvas = document.createElement("canvas");
  sourceCanvas.width = image.naturalWidth || image.width;
  sourceCanvas.height = image.naturalHeight || image.height;
  const sourceContext = sourceCanvas.getContext("2d", { willReadFrequently: true });
  sourceContext.drawImage(image, 0, 0);
  const pixels = sourceContext.getImageData(0, 0, sourceCanvas.width, sourceCanvas.height).data;
  let minX = sourceCanvas.width;
  let minY = sourceCanvas.height;
  let maxX = -1;
  let maxY = -1;
  for (let y = 0; y < sourceCanvas.height; y += 1) {
    for (let x = 0; x < sourceCanvas.width; x += 1) {
      const index = (y * sourceCanvas.width + x) * 4;
      const visible = pixels[index + 3] > 12
        && (pixels[index] < 248 || pixels[index + 1] < 248 || pixels[index + 2] < 248);
      if (!visible) continue;
      minX = Math.min(minX, x);
      minY = Math.min(minY, y);
      maxX = Math.max(maxX, x);
      maxY = Math.max(maxY, y);
    }
  }
  if (maxX < minX || maxY < minY) return dataUrl;
  const padding = Math.max(2, Math.round(Math.min(sourceCanvas.width, sourceCanvas.height) * 0.01));
  minX = Math.max(0, minX - padding);
  minY = Math.max(0, minY - padding);
  maxX = Math.min(sourceCanvas.width - 1, maxX + padding);
  maxY = Math.min(sourceCanvas.height - 1, maxY + padding);
  const cropWidth = maxX - minX + 1;
  const cropHeight = maxY - minY + 1;
  const outputCanvas = document.createElement("canvas");
  outputCanvas.width = cropWidth;
  outputCanvas.height = cropHeight;
  outputCanvas.getContext("2d").drawImage(sourceCanvas, minX, minY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
  return outputCanvas.toDataURL("image/png");
}

async function cropSignatureToMainStrokeDataUrl(dataUrl) {
  if (!dataUrl) return "";
  const image = await new Promise((resolve, reject) => {
    const loadedImage = new Image();
    loadedImage.onload = () => resolve(loadedImage);
    loadedImage.onerror = () => reject(new Error("No se pudo procesar la firma"));
    loadedImage.src = dataUrl;
  });

  const width = image.naturalWidth || image.width;
  const height = image.naturalHeight || image.height;
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext("2d", { willReadFrequently: true });
  context.drawImage(image, 0, 0);
  const data = context.getImageData(0, 0, width, height).data;

  const total = width * height;
  const mask = new Uint8Array(total);
  const visited = new Uint8Array(total);

  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const idx = y * width + x;
      const p = idx * 4;
      const alpha = data[p + 3];
      const r = data[p];
      const g = data[p + 1];
      const b = data[p + 2];
      if (alpha > 16 && (r < 245 || g < 245 || b < 245)) {
        mask[idx] = 1;
      }
    }
  }

  const queue = new Int32Array(total);
  let bestArea = 0;
  let bestBox = null;
  const neighbors = [-1, 1, -width, width];

  for (let start = 0; start < total; start += 1) {
    if (!mask[start] || visited[start]) continue;
    let head = 0;
    let tail = 0;
    queue[tail++] = start;
    visited[start] = 1;

    let area = 0;
    let minX = width;
    let minY = height;
    let maxX = -1;
    let maxY = -1;

    while (head < tail) {
      const current = queue[head++];
      const cy = Math.floor(current / width);
      const cx = current - cy * width;
      area += 1;
      if (cx < minX) minX = cx;
      if (cy < minY) minY = cy;
      if (cx > maxX) maxX = cx;
      if (cy > maxY) maxY = cy;

      for (let i = 0; i < neighbors.length; i += 1) {
        const next = current + neighbors[i];
        if (next < 0 || next >= total) continue;
        if (neighbors[i] === -1 && cx === 0) continue;
        if (neighbors[i] === 1 && cx === width - 1) continue;
        if (neighbors[i] === -width && cy === 0) continue;
        if (neighbors[i] === width && cy === height - 1) continue;
        if (!mask[next] || visited[next]) continue;
        visited[next] = 1;
        queue[tail++] = next;
      }
    }

    if (area > bestArea) {
      bestArea = area;
      bestBox = { minX, minY, maxX, maxY };
    }
  }

  if (!bestBox || bestArea < 20) {
    return dataUrl;
  }

  const padding = Math.max(2, Math.round(Math.min(width, height) * 0.02));
  const cropMinX = Math.max(0, bestBox.minX - padding);
  const cropMinY = Math.max(0, bestBox.minY - padding);
  const cropMaxX = Math.min(width - 1, bestBox.maxX + padding);
  const cropMaxY = Math.min(height - 1, bestBox.maxY + padding);
  const cropWidth = cropMaxX - cropMinX + 1;
  const cropHeight = cropMaxY - cropMinY + 1;

  const output = document.createElement("canvas");
  output.width = cropWidth;
  output.height = cropHeight;
  output.getContext("2d").drawImage(canvas, cropMinX, cropMinY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
  return output.toDataURL("image/png");
}

async function fetchConfiguracionClinica() {
  const response = await authFetch("api_configuracion.php", { method: "GET" });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || payload?.success === false) {
    throw new Error(payload?.error || "No se pudo cargar configuracion clinica");
  }
  return payload?.data || payload || {};
}

function normalizeDocDate(input) {
  const value = String(input || "").trim();
  if (!value) return "-";
  const parts = value.split("-");
  if (parts.length === 3 && parts[0].length === 4) return `${parts[2]}-${parts[1]}-${parts[0]}`;
  return value;
}

function normalizePsicoValue(value, fallback = "-") {
  const text = String(value || "").trim();
  return text || fallback;
}

function psicoOptionLabel(value, map) {
  const key = String(value || "").trim().toLowerCase();
  return map[key] || normalizePsicoValue(value, "-");
}

function normalizeCompareText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toUpperCase()
    .replace(/[^A-Z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function simplifyProfessionalName(value) {
  return normalizeCompareText(value)
    .replace(/^(DRA|DR|PSIC|OBST|OD|LIC|NUT|PROF)\s+/g, "")
    .trim();
}

function rutaExamen(basePath, ordenId, detalleId) {
  return `${basePath}/${ordenId}/examen/${detalleId}`;
}

function estadoVisual(item, resultado) {
  if (String(item?.estado_ejecucion || "") === "observado") return "observado";
  if (String(resultado?.estado || "") === "finalizado") return "finalizado";
  if (resultado?.id || ["en_proceso", "realizado"].includes(String(item?.estado_ejecucion || ""))) return "borrador";
  return "pendiente";
}

function ResultadoBadge({ estado }) {
  const config = RESULTADO_ESTADOS[estado] || RESULTADO_ESTADOS.pendiente;
  const Icon = config.icon;
  return <span className={`inline-flex items-center gap-1.5 rounded border px-2.5 py-1 text-xs font-semibold ${config.className}`}><Icon aria-hidden="true" />{config.label}</span>;
}

function resumirParametrosLaboratorio(parametros) {
  if (!Array.isArray(parametros) || parametros.length === 0) return "";
  return parametros
    .filter((parametro) => parametro && typeof parametro === "object")
    .map((parametro) => {
      const nombre = String(parametro.nombre || "").trim();
      const valor = String(parametro.valor || "").trim();
      const unidad = String(parametro.unidad || "").trim();
      if (!nombre || !valor) return "";
      return unidad ? `${nombre}: ${valor} ${unidad}` : `${nombre}: ${valor}`;
    })
    .filter((linea) => linea !== "")
    .join("\n");
}

function normalizarDatosLabBasico(datosEntrada) {
  const datos = (datosEntrada && typeof datosEntrada === "object") ? structuredClone(datosEntrada) : {};
  const parametros = Array.isArray(datos.parametros) ? datos.parametros.filter((p) => p && typeof p === "object") : [];
  const resumen = String(datos.resultado_laboratorio_resumen || "").trim();
  const existeParametroCompleto = parametros.some((parametro) => {
    const nombre = String(parametro.nombre || "").trim();
    const valor = String(parametro.valor || "").trim();
    return nombre !== "" && valor !== "";
  });

  if (resumen && !existeParametroCompleto) {
    parametros.unshift({
      grupo: "LABORATORIO",
      nombre: "Laboratorio",
      valor: resumen,
      unidad: "",
      referencia: "",
    });
  }

  if (!resumen) {
    const resumenAuto = resumirParametrosLaboratorio(parametros);
    if (resumenAuto) {
      datos.resultado_laboratorio_resumen = resumenAuto;
    }
  }

  datos.parametros = parametros;
  return datos;
}

export default function FormatoClinicoExamenPage() {
  const params = useParams();
  const location = useLocation();
  const ordenId = Number(params.ordenId || 0);
  const detalleId = Number(params.detalleId || 0);
  const [orden, setOrden] = useState(null);
  const [resultadoContexto, setResultadoContexto] = useState(null);
  const [form, setForm] = useState({ formatoCodigo: "", datos: {}, observacion: "" });
  const [plantillaId, setPlantillaId] = useState("0");
  const [plantillaNombre, setPlantillaNombre] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [templateSaving, setTemplateSaving] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [profesionales, setProfesionales] = useState([]);

  const cargarProfesionales = useCallback(async () => {
    try {
      const response = await authFetch("api_medicos.php", { method: "GET" });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.success || !Array.isArray(payload.medicos)) {
        setProfesionales([]);
        return;
      }

      const lista = payload.medicos
        .map((row) => ({
          id: Number(row?.id || 0),
          nombre: String(row?.nombre || "").trim(),
          apellido: String(row?.apellido || "").trim(),
          tipo_profesional: String(row?.tipo_profesional || "medico").trim().toLowerCase(),
          abreviatura_profesional: String(row?.abreviatura_profesional || "").trim(),
          colegio_sigla: String(row?.colegio_sigla || "").trim(),
          nro_colegiatura: String(row?.nro_colegiatura || row?.cmp || "").trim(),
          cmp: String(row?.cmp || "").trim(),
          firma: String(row?.firma || "").trim(),
        }))
        .filter((row) => row.id > 0)
        .sort((a, b) => `${a.apellido} ${a.nombre}`.localeCompare(`${b.apellido} ${b.nombre}`));
      setProfesionales(lista);
    } catch {
      setProfesionales([]);
    }
  }, []);

  const cargar = useCallback(async () => {
    if (ordenId <= 0 || detalleId <= 0) {
      setError("La ruta del examen no es válida");
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const [ordenData, resultadoData] = await Promise.all([
        obtenerDetalleOrdenOcupacional(ordenId),
        obtenerResultadoClinicoOcupacional({ ordenDetalleId: detalleId }),
      ]);
      const item = (ordenData?.items || []).find((candidate) => Number(candidate.id) === detalleId);
      if (!item || Number(resultadoData?.detalle?.orden_id || 0) !== ordenId) {
        throw new Error("El examen no pertenece a la orden indicada");
      }
      const resultado = resultadoData?.data || null;
      const sugerida = resultadoData?.plantillaSugerida;
      const plantillaBase = sugerida && typeof sugerida === "object" ? sugerida : {};
      const datosGuardados = resultado?.datos_json && typeof resultado.datos_json === "object" ? resultado.datos_json : {};
      const datos = resultado?.id ? { ...plantillaBase, ...datosGuardados } : { ...plantillaBase };
      const nombreResponsable = String(ordenData?.medico_nombre_snapshot || ordenData?.medico_responsable || "").trim();
      if (!resultado?.id && nombreResponsable && Object.prototype.hasOwnProperty.call(datos, "responsable_evaluacion")) {
        const actual = String(datos.responsable_evaluacion || "").trim();
        if (!actual) {
          datos.responsable_evaluacion = nombreResponsable;
        }
      }
      const plantillas = Array.isArray(resultadoData?.plantillasDisponibles) ? resultadoData.plantillasDisponibles : [];
      setOrden(ordenData);
      setResultadoContexto(resultadoData);
      setForm({
        formatoCodigo: String(resultado?.formato_codigo || resultadoData?.detalle?.formato_codigo || item.examen_codigo || "formato_general").toLowerCase(),
        datos,
        observacion: String(resultado?.observacion || item.observacion_ejecucion || ""),
      });
      setPlantillaId(plantillas.length > 0 ? String(plantillas[0].id || 0) : "0");
      setPlantillaNombre(`${String(item.examen_codigo || "examen").toLowerCase()}_plantilla`);
    } catch (err) {
      setOrden(null);
      setResultadoContexto(null);
      setError(err.message || "No se pudo cargar el examen");
    } finally {
      setLoading(false);
    }
  }, [detalleId, ordenId]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  useEffect(() => {
    cargarProfesionales();
  }, [cargarProfesionales]);

  const itemIndex = useMemo(
    () => (orden?.items || []).findIndex((candidate) => Number(candidate.id) === detalleId),
    [detalleId, orden]
  );
  const item = itemIndex >= 0 ? orden.items[itemIndex] : null;
  const anterior = itemIndex > 0 ? orden.items[itemIndex - 1] : null;
  const siguiente = itemIndex >= 0 && itemIndex < orden.items.length - 1 ? orden.items[itemIndex + 1] : null;
  const resultado = resultadoContexto?.data || null;
  const plantillas = resultadoContexto?.plantillasDisponibles || [];
  const readOnly = ["cerrada", "anulada"].includes(String(orden?.estado || ""));
  const estado = estadoVisual(item, resultado);
  const templateCode = String(resultadoContexto?.detalle?.template_code || "general_basico");
  const mostrarSignosVitales = ["lab_basico", "psicologia_basica", "epworth_test", "fobia_estres"].includes(templateCode);
  const triaje = orden?.triaje && typeof orden.triaje === "object" ? orden.triaje : {};
  const examenCodigoActual = String(item?.examen_codigo || "").toUpperCase();
  const pruebasPsicologiaRelacionadas = useMemo(() => {
    const items = Array.isArray(orden?.items) ? orden.items : [];
    const objetivos = new Set(["PSI_0001", "EPW_0001", "FOBIA"]);
    return items
      .filter((candidate) => objetivos.has(String(candidate?.examen_codigo || "").toUpperCase()))
      .map((candidate) => ({
        id: Number(candidate.id || 0),
        codigo: String(candidate.examen_codigo || "").toUpperCase(),
        descripcion: String(candidate.examen_descripcion || "Examen psicológico"),
      }))
      .filter((candidate) => candidate.id > 0)
      .sort((a, b) => a.codigo.localeCompare(b.codigo));
  }, [orden]);
  const mostrarNavegacionPsicologia = ["PSI_0001", "EPW_0001", "FOBIA"].includes(examenCodigoActual)
    || ["psicologia_basica", "epworth_test", "fobia_estres"].includes(templateCode);
  const esVistaEnfermeria = location.pathname.startsWith("/mis-triajes-ocupacionales");
  const baseEvaluacionesPath = esVistaEnfermeria
    ? "/mis-triajes-ocupacionales"
    : (location.pathname.startsWith("/mis-evaluaciones-ocupacionales")
      ? "/mis-evaluaciones-ocupacionales"
      : "/salud-ocupacional/evaluacion-medica");
  const etiquetaRetorno = esVistaEnfermeria ? "Triaje ocupacional" : "Evaluación médica ocupacional";

  const responsableProfesionalId = Number(form?.datos?.responsable_profesional_id || 0);
  const responsableProfesional = useMemo(
    () => profesionales.find((profesional) => Number(profesional.id) === responsableProfesionalId) || null,
    [profesionales, responsableProfesionalId]
  );
  const responsableNombreVisible = String(
    form?.datos?.responsable_evaluacion
    || (responsableProfesional ? formatProfesionalName(responsableProfesional) : "")
    || orden?.medico_nombre_snapshot
    || orden?.medico_responsable
    || "No asignado"
  ).trim();
  const responsableColegiaturaVisible = String(
    form?.datos?.responsable_colegiatura
    || (responsableProfesional ? formatColegiatura(responsableProfesional) : "")
    || (orden?.medico_cmp_snapshot ? `CMP ${orden.medico_cmp_snapshot}` : "")
    || "Sin colegiatura registrada"
  ).trim();
  const responsableProfesionalFirma = useMemo(() => {
    const firmaDirecta = String(responsableProfesional?.firma || "").trim();
    if (firmaDirecta) return responsableProfesional;

    const responsableSimple = simplifyProfessionalName(form?.datos?.responsable_evaluacion || responsableNombreVisible);
    if (!responsableSimple) return null;

    const candidatos = profesionales.filter((profesional) => {
      const nombreSimple = simplifyProfessionalName(formatProfesionalName(profesional));
      if (!nombreSimple) return false;
      return nombreSimple === responsableSimple
        || nombreSimple.includes(responsableSimple)
        || responsableSimple.includes(nombreSimple);
    });

    const conFirma = candidatos.find((profesional) => String(profesional?.firma || "").trim() !== "");
    return conFirma || null;
  }, [form?.datos?.responsable_evaluacion, profesionales, responsableNombreVisible, responsableProfesional]);

  const onChangeResponsableProfesional = (value) => {
    const id = Number(value || 0);
    if (id <= 0) {
      setForm((current) => ({
        ...current,
        datos: {
          ...(current.datos || {}),
          responsable_profesional_id: "",
          responsable_tipo_profesional: "",
          responsable_colegiatura: "",
        },
      }));
      return;
    }

    const profesional = profesionales.find((row) => Number(row.id) === id);
    if (!profesional) return;

    const nombre = formatProfesionalName(profesional);
    const colegiatura = formatColegiatura(profesional);
    setForm((current) => ({
      ...current,
      datos: {
        ...(current.datos || {}),
        responsable_profesional_id: String(profesional.id),
        responsable_tipo_profesional: String(profesional.tipo_profesional || "").trim().toLowerCase(),
        responsable_colegiatura: colegiatura,
        responsable_evaluacion: nombre,
      },
    }));
  };

  const cambiarDato = (key, value) => {
    setForm((current) => {
      const datos = { ...(current.datos || {}), [key]: value };
      if (["peso_kg", "talla_cm"].includes(key)) {
        const peso = Number(key === "peso_kg" ? value : datos.peso_kg);
        const tallaCm = Number(key === "talla_cm" ? value : datos.talla_cm);
        datos.imc = peso > 0 && tallaCm > 0 ? (peso / ((tallaCm / 100) ** 2)).toFixed(2) : "";
      }
      return { ...current, datos };
    });
  };

  const cambiarAudiometria = (oido, frecuencia, value) => {
    setForm((current) => ({
      ...current,
      datos: { ...current.datos, [oido]: { ...(current.datos?.[oido] || {}), [frecuencia]: value } },
    }));
  };

  const cambiarParametro = (index, key, value) => {
    setForm((current) => ({
      ...current,
      datos: {
        ...current.datos,
        parametros: Array.isArray(current.datos?.parametros)
          ? current.datos.parametros.map((parametro, currentIndex) => currentIndex === index ? { ...parametro, [key]: value } : parametro)
          : [],
      },
    }));
  };

  const guardar = async (estadoResultado) => {
    if (readOnly || saving) return;
    setSaving(true);
    setError("");
    setMessage("");
    try {
      const datosJson = templateCode === "lab_basico"
        ? normalizarDatosLabBasico(form.datos)
        : form.datos;

      await guardarResultadoClinicoOcupacional({
        ordenDetalleId: detalleId,
        formatoCodigo: form.formatoCodigo,
        datosJson,
        estado: estadoResultado,
        observacion: form.observacion,
      });
      setMessage(estadoResultado === "finalizado" ? "Examen finalizado correctamente" : "Borrador guardado correctamente");
      await cargar();
    } catch (err) {
      setError(err.message || "No se pudo guardar el resultado clínico");
    } finally {
      setSaving(false);
    }
  };

  const aplicarPlantilla = () => {
    const selected = plantillas.find((template) => String(template.id || 0) === plantillaId);
    if (!selected?.datos_json || typeof selected.datos_json !== "object") {
      setError("Seleccione una plantilla válida");
      return;
    }
    setForm((current) => ({ ...current, datos: structuredClone(selected.datos_json) }));
    setError("");
  };

  const guardarPlantilla = async () => {
    if (readOnly || templateSaving) return;
    const nombre = plantillaNombre.trim();
    if (!nombre) {
      setError("Ingrese un nombre para la plantilla");
      return;
    }
    setTemplateSaving(true);
    setError("");
    try {
      await guardarPlantillaResultadoClinicoOcupacional({
        ordenDetalleId: detalleId,
        nombre,
        templateCode: resultadoContexto?.detalle?.template_code || "",
        examenCodigo: item?.examen_codigo || "",
        examenDescripcion: item?.examen_descripcion || "",
        formatoCodigo: form.formatoCodigo,
        datosJson: form.datos,
      });
      setMessage("Plantilla guardada en el catálogo");
      await cargar();
    } catch (err) {
      setError(err.message || "No se pudo guardar la plantilla");
    } finally {
      setTemplateSaving(false);
    }
  };

  const eliminarPlantilla = async () => {
    const selected = plantillas.find((template) => String(template.id || 0) === plantillaId);
    if (readOnly || Number(selected?.id || 0) <= 0) return;
    if (!window.confirm(`¿Eliminar la plantilla "${selected.nombre || selected.codigo}"?`)) return;
    setTemplateSaving(true);
    setError("");
    try {
      await eliminarPlantillaResultadoClinicoOcupacional(selected.id, { ordenDetalleId: detalleId });
      setMessage("Plantilla eliminada del catálogo");
      await cargar();
    } catch (err) {
      setError(err.message || "No se pudo eliminar la plantilla");
    } finally {
      setTemplateSaving(false);
    }
  };

  const imprimir = async () => {
    if (String(resultado?.estado || "") !== "finalizado") return;
    try {
      await registrarEmisionPdfResultadoClinicoOcupacional({ ordenDetalleId: detalleId, formatoCodigo: form.formatoCodigo });

      if (templateCode !== "psicologia_basica") {
        window.print();
        return;
      }

      const jsPDF = (await import("jspdf")).default;
      const [configuracionClinica, logoRawDataUrl] = await Promise.all([
        fetchConfiguracionClinica().catch(() => ({})),
        Promise.resolve(""),
      ]);

      const logoUrl = resolveAssetUrl(configuracionClinica?.logo_ocupacional_url || configuracionClinica?.logo_url || "");
      const logoSelloUrl = resolveAssetUrl(configuracionClinica?.logo_url || configuracionClinica?.logo_ocupacional_url || "");
      let firmaRaw = String(responsableProfesionalFirma?.firma || "").trim();
      if (!firmaRaw) {
        const tipoResponsable = String(form?.datos?.responsable_tipo_profesional || "").trim().toLowerCase();
        if (tipoResponsable === "medico") {
          firmaRaw = String(orden?.medico_firma_snapshot || "").trim();
        }
      }
      const firmaUrl = /^(data:|https?:\/\/|blob:|uploads\/|\/uploads\/)/i.test(firmaRaw)
        ? resolveAssetUrl(firmaRaw)
        : "";
      const [logoDataUrlSource, firmaDataUrlRaw, logoSelloDataUrlRaw] = await Promise.all([
        logoUrl ? loadImageAsDataUrl(logoUrl).catch(() => "") : Promise.resolve(logoRawDataUrl),
        firmaUrl ? loadImageAsDataUrl(firmaUrl).catch(() => "") : Promise.resolve(""),
        logoSelloUrl ? loadImageAsDataUrl(logoSelloUrl).catch(() => "") : Promise.resolve(""),
      ]);
      const logoDataUrl = logoDataUrlSource
        ? await cropImageWhitespaceDataUrl(logoDataUrlSource).catch(() => logoDataUrlSource)
        : "";
      const logoSelloDataUrl = logoSelloDataUrlRaw
        ? await cropImageWhitespaceDataUrl(logoSelloDataUrlRaw).catch(() => logoSelloDataUrlRaw)
        : "";
      const firmaDataUrl = firmaDataUrlRaw
        ? await cropSignatureToMainStrokeDataUrl(firmaDataUrlRaw).catch(() => firmaDataUrlRaw)
        : "";

      const doc = new jsPDF({ unit: "mm", format: "a4" });
      const datos = form?.datos && typeof form.datos === "object" ? form.datos : {};
      const responsable = normalizePsicoValue(datos.responsable_evaluacion || responsableNombreVisible, "No consignado");
      const motivo = normalizePsicoValue(datos.motivo_evaluacion, "EVALUACION MEDICA OCUPACIONAL");
      const fechaEvaluacion = normalizeDocDate(orden?.fecha_orden || "");

      const drawCell = (x, y, w, h, text, options = {}) => {
        doc.setDrawColor(0, 0, 0);
        doc.setLineWidth(0.2);
        doc.rect(x, y, w, h);
        doc.setFont("times", options.bold ? "bold" : "normal");
        doc.setFontSize(options.fontSize || 8.5);
        const paddingX = 1.6;
        const innerWidth = Math.max(4, w - paddingX * 2);
        const lines = doc.splitTextToSize(String(text || ""), innerWidth);
        const maxLines = options.maxLines || Math.max(1, Math.floor((h - 1.8) / ((options.fontSize || 8.5) * 0.38)));
        const visibleLines = lines.slice(0, maxLines);
        if (lines.length > maxLines && visibleLines.length > 0) {
          visibleLines[visibleLines.length - 1] = `${String(visibleLines[visibleLines.length - 1]).slice(0, -3)}...`;
        }
        const lineHeight = (options.fontSize || 8.5) * 0.38;
        const textY = y + 2.9;
        const textX = options.center ? x + w / 2 : x + paddingX;
        doc.text(visibleLines, textX, textY, { align: options.center ? "center" : "left", baseline: "top" });
      };

      const mark = (isSelected) => (isSelected ? "X" : "");
      const x = 14;
      const width = 182;

      doc.setDrawColor(147, 197, 253);
      doc.setLineWidth(0.8);
      doc.rect(10, 8, 190, 281);
      doc.setLineWidth(0.35);
      doc.rect(12, 10, 186, 277);

      if (logoDataUrl) {
        const logoProps = doc.getImageProperties(logoDataUrl);
        const ratio = logoProps.width / logoProps.height;
        const logoWidth = Math.min(124, 26 * ratio);
        const logoHeight = logoWidth / ratio;
        doc.addImage(logoDataUrl, inferDataUrlImageFormat(logoDataUrl), 105 - logoWidth / 2, 12 + (26 - logoHeight) / 2, logoWidth, logoHeight);
      } else {
        doc.setFont("times", "bold");
        doc.setFontSize(18);
        doc.text(String(configuracionClinica?.nombre_clinica || "CLINICA 2 DE MAYO"), 105, 24.5, { align: "center" });
      }

      doc.setFont("times", "bold");
      doc.setFontSize(11);
      doc.text("INFORME PSICOLOGICO OCUPACIONAL", 105, 42, { align: "center" });

      let y = 46;
      drawCell(x, y, width, 9, "DATOS PERSONALES", { bold: true, center: true, fontSize: 9.2 });
      y += 9;
      drawCell(x, y, 46, 8, "APELLIDOS Y NOMBRES", { bold: true, fontSize: 7.2 });
      drawCell(x + 46, y, 136, 8, normalizePsicoValue(orden?.paciente_nombre_completo), { fontSize: 8.3 });
      y += 8;

      drawCell(x, y, 26, 8, "DNI", { bold: true, center: true, fontSize: 7.8 });
      drawCell(x + 26, y, 34, 8, normalizePsicoValue(orden?.documento_numero), { center: true });
      drawCell(x + 60, y, 26, 8, "EDAD", { bold: true, center: true, fontSize: 7.8 });
      drawCell(x + 86, y, 20, 8, normalizePsicoValue(orden?.paciente_edad), { center: true });
      drawCell(x + 106, y, 18, 8, "SEXO", { bold: true, center: true, fontSize: 7.8 });
      drawCell(x + 124, y, 18, 8, normalizePsicoValue(orden?.paciente_sexo), { center: true });
      drawCell(x + 142, y, 20, 8, "FECHA", { bold: true, center: true, fontSize: 7.8 });
      drawCell(x + 162, y, 20, 8, fechaEvaluacion, { center: true, fontSize: 8.1 });
      y += 8;

      drawCell(x, y, 40, 8, "EMPRESA", { bold: true, fontSize: 7.8 });
      drawCell(x + 40, y, 142, 8, normalizePsicoValue(orden?.empresa), { fontSize: 8.1, maxLines: 1 });
      y += 8;
      drawCell(x, y, 40, 8, "PUESTO", { bold: true, fontSize: 7.8 });
      drawCell(x + 40, y, 142, 8, normalizePsicoValue(orden?.puesto_trabajo), { fontSize: 8.1, maxLines: 1 });
      y += 8;
      drawCell(x, y, 40, 8, "RESPONSABLE", { bold: true, fontSize: 7.8 });
      drawCell(x + 40, y, 142, 8, responsable, { fontSize: 8.1, maxLines: 1 });
      y += 8;
      drawCell(x, y, 40, 8, "MOTIVO", { bold: true, fontSize: 7.8 });
      drawCell(x + 40, y, 142, 8, motivo, { fontSize: 8.1, maxLines: 1 });
      y += 10;

      drawCell(x, y, width, 8.5, "OBSERVACION DE CONDUCTAS", { bold: true, center: true, fontSize: 9.0 });
      y += 8.5;
      drawCell(x, y, 32, 7.5, "Presentacion", { bold: true, fontSize: 7.7 });
      drawCell(x + 32, y, 30, 7.5, psicoOptionLabel(datos.presentacion, { adecuada: "Adecuada", inadecuada: "Inadecuada" }), { center: true, fontSize: 8.0 });
      drawCell(x + 62, y, 28, 7.5, "Postura", { bold: true, fontSize: 7.7 });
      drawCell(x + 90, y, 30, 7.5, psicoOptionLabel(datos.postura, { erguida: "Erguida", encorvada: "Encorvada" }), { center: true, fontSize: 8.0 });
      drawCell(x + 120, y, 32, 7.5, "Ritmo discurso", { bold: true, fontSize: 7.2 });
      drawCell(x + 152, y, 30, 7.5, psicoOptionLabel(datos.discurso_ritmo, { lento: "Lento", rapido: "Rapido", fluido: "Fluido" }), { center: true, fontSize: 8.0 });
      y += 7.5;

      drawCell(x, y, 32, 7.5, "Tono", { bold: true, fontSize: 7.7 });
      drawCell(x + 32, y, 30, 7.5, psicoOptionLabel(datos.discurso_tono, { bajo: "Bajo", moderado: "Moderado", alto: "Alto" }), { center: true, fontSize: 8.0 });
      drawCell(x + 62, y, 28, 7.5, "Articulacion", { bold: true, fontSize: 7.7 });
      drawCell(x + 90, y, 30, 7.5, psicoOptionLabel(datos.discurso_articulacion, { con_dificultad: "Con dificultad", sin_dificultad: "Sin dificultad" }), { center: true, fontSize: 7.4 });
      drawCell(x + 120, y, 32, 7.5, "Orient. tiempo", { bold: true, fontSize: 7.2 });
      drawCell(x + 152, y, 30, 7.5, psicoOptionLabel(datos.orientacion_tiempo, { orientado: "Orientado", desorientado: "Desorientado" }), { center: true, fontSize: 8.0 });
      y += 7.5;

      drawCell(x, y, 32, 7.5, "Orient. espacio", { bold: true, fontSize: 7.2 });
      drawCell(x + 32, y, 30, 7.5, psicoOptionLabel(datos.orientacion_espacio, { orientado: "Orientado", desorientado: "Desorientado" }), { center: true, fontSize: 8.0 });
      drawCell(x + 62, y, 28, 7.5, "Orient. persona", { bold: true, fontSize: 7.2 });
      drawCell(x + 90, y, 30, 7.5, psicoOptionLabel(datos.orientacion_persona, { orientado: "Orientado", desorientado: "Desorientado" }), { center: true, fontSize: 8.0 });
      drawCell(x + 120, y, 62, 7.5, "", { fontSize: 8 });
      y += 9.5;

      drawCell(x, y, width, 8.5, "RESULTADOS DE LA EVALUACION", { bold: true, center: true, fontSize: 9.0 });
      y += 8.5;
      drawCell(x, y, 50, 7.5, "Nivel intelectual", { bold: true, fontSize: 7.7 });
      drawCell(x + 50, y, 41, 7.5, normalizePsicoValue(datos.nivel_intelectual), { fontSize: 8.0, maxLines: 1 });
      drawCell(x + 91, y, 50, 7.5, "Coordinacion visomotriz", { bold: true, fontSize: 7.4 });
      drawCell(x + 141, y, 41, 7.5, normalizePsicoValue(datos.coordinacion_visomotriz), { fontSize: 8.0, maxLines: 1 });
      y += 7.5;
      drawCell(x, y, 50, 7.5, "Nivel de memoria", { bold: true, fontSize: 7.7 });
      drawCell(x + 50, y, 41, 7.5, normalizePsicoValue(datos.nivel_memoria), { fontSize: 8.0, maxLines: 1 });
      drawCell(x + 91, y, 50, 7.5, "Personalidad", { bold: true, fontSize: 7.7 });
      drawCell(x + 141, y, 41, 7.5, normalizePsicoValue(datos.personalidad), { fontSize: 8.0, maxLines: 1 });
      y += 7.5;
      drawCell(x, y, 50, 7.5, "Afectividad", { bold: true, fontSize: 7.7 });
      drawCell(x + 50, y, 132, 7.5, normalizePsicoValue(datos.afectividad), { fontSize: 8.0, maxLines: 1 });
      y += 9.5;

      drawCell(x, y, width, 8.5, "CONCLUSIONES Y RECOMENDACIONES", { bold: true, center: true, fontSize: 9.0 });
      y += 8.5;
      drawCell(x, y, 37, 14, "Area cognitiva", { bold: true, fontSize: 7.6 });
      drawCell(x + 37, y, 145, 14, normalizePsicoValue(datos.conclusion_cognitiva), { fontSize: 8.0, maxLines: 3 });
      y += 14;
      drawCell(x, y, 37, 14, "Area emocional", { bold: true, fontSize: 7.6 });
      drawCell(x + 37, y, 145, 14, normalizePsicoValue(datos.conclusion_emocional), { fontSize: 8.0, maxLines: 3 });
      y += 14;
      drawCell(x, y, 37, 12, "Recomendaciones", { bold: true, fontSize: 7.6 });
      drawCell(x + 37, y, 145, 12, normalizePsicoValue(datos.recomendaciones), { fontSize: 8.0, maxLines: 3 });

      const recomendacionesBottomY = y + 12;
      const firmaY = Math.min(257, recomendacionesBottomY + 9);

      const selloY = Math.max(recomendacionesBottomY + 1.5, firmaY - 8.5);
      const firmaCenterX = 105;
      const clinicName = "CLINICA DOS DE MAYO PUCALLPA";
      doc.setFont("times", "bold");
      doc.setFontSize(8.6);
      const clinicTextWidth = doc.getTextWidth(clinicName);
      const logoMaxWidth = 16;
      const logoMaxHeight = 7;
      const logoTextGap = 2;
      let selloLogoWidth = 0;
      let selloLogoHeight = 0;

      if (logoSelloDataUrl) {
        const selloProps = doc.getImageProperties(logoSelloDataUrl);
        const selloRatio = selloProps.width / selloProps.height;
        selloLogoWidth = Math.min(logoMaxWidth, logoMaxHeight * selloRatio);
        selloLogoHeight = selloLogoWidth / selloRatio;
      }
      const headerGroupWidth = clinicTextWidth + (selloLogoWidth > 0 ? selloLogoWidth + logoTextGap : 0);
      const headerStartX = firmaCenterX - (headerGroupWidth / 2);

      if (logoSelloDataUrl && selloLogoWidth > 0 && selloLogoHeight > 0) {
        doc.addImage(
          logoSelloDataUrl,
          inferDataUrlImageFormat(logoSelloDataUrl),
          headerStartX,
          selloY + (7 - selloLogoHeight) / 2,
          selloLogoWidth,
          selloLogoHeight
        );
      }
      const clinicTextX = headerStartX + (selloLogoWidth > 0 ? selloLogoWidth + logoTextGap : 0);
      doc.text(clinicName, clinicTextX, selloY + 4.8);

      if (firmaDataUrl) {
        doc.addImage(firmaDataUrl, inferDataUrlImageFormat(firmaDataUrl), 82, firmaY, 46, 17);
      }
      doc.setDrawColor(0, 0, 0);
      doc.setLineWidth(0.25);
      doc.line(74, firmaY + 18, 136, firmaY + 18);
      doc.setFont("times", "normal");
      doc.setFontSize(8.3);
      doc.text(responsable, 105, firmaY + 23, { align: "center" });
      doc.text(normalizePsicoValue(responsableColegiaturaVisible, "Sin colegiatura"), 105, firmaY + 27, { align: "center" });

      const safeOrder = String(orden?.codigo || `orden_${ordenId}`).replace(/[^A-Za-z0-9_-]/g, "_");
      doc.save(`resultado_psicologico_${safeOrder}.pdf`);
    } catch (err) {
      setError(err.message || "No se pudo preparar la impresión");
    }
  };

  if (loading) {
    return <div className="flex min-h-screen items-center justify-center bg-slate-50 text-sm font-medium text-cyan-700">Cargando examen clínico...</div>;
  }

  if (!orden || !item || !resultadoContexto) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-50 p-4">
        <div className="w-full max-w-lg border border-red-200 bg-white p-6 text-center shadow-sm">
          <FiAlertCircle className="mx-auto text-3xl text-red-600" />
          <h1 className="mt-3 text-lg font-bold text-slate-900">No se pudo abrir el examen</h1>
          <p className="mt-2 text-sm text-red-700">{error || "El examen solicitado no existe"}</p>
          <Link className="mt-5 inline-flex items-center gap-2 rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700" to={baseEvaluacionesPath}><FiArrowLeft />Volver a evaluaciones</Link>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-[linear-gradient(180deg,#f8fafc_0%,#ecfeff_100%)] px-3 py-4 print:bg-white print:p-0 sm:px-5 lg:px-7">
      <div className="mx-auto max-w-[1500px] space-y-4">
        <header className="border-b border-cyan-200 pb-4 print:border-slate-400">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
              <Link className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase text-cyan-700 hover:text-cyan-900 print:hidden" to={baseEvaluacionesPath}><FiArrowLeft />{etiquetaRetorno}</Link>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{item.examen_descripcion || "Examen ocupacional"}</h1>
                <ResultadoBadge estado={estado} />
              </div>
              <p className="mt-1 text-sm text-slate-600">{item.examen_codigo || "Sin código"} · {item.examen_grupo || "Examen clínico"}</p>
            </div>
            <div className="flex items-center gap-2 print:hidden">
              {!esVistaEnfermeria && anterior ? <Link className="inline-flex h-10 items-center gap-1 rounded border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50" to={rutaExamen(baseEvaluacionesPath, ordenId, anterior.id)} title={anterior.examen_descripcion}><FiChevronLeft />Anterior</Link> : null}
              {!esVistaEnfermeria && siguiente ? <Link className="inline-flex h-10 items-center gap-1 rounded border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50" to={rutaExamen(baseEvaluacionesPath, ordenId, siguiente.id)} title={siguiente.examen_descripcion}>Siguiente<FiChevronRight /></Link> : null}
            </div>
          </div>
        </header>

        <section className="grid grid-cols-2 gap-x-5 gap-y-3 border-y border-slate-200 bg-white px-4 py-3 text-xs shadow-sm sm:grid-cols-3 lg:grid-cols-6" aria-label="Contexto del examen">
          <div className="col-span-2 sm:col-span-1"><p className="font-semibold uppercase text-slate-500">Paciente</p><p className="mt-0.5 font-semibold text-slate-900">{orden.paciente_nombre_completo || "-"}</p></div>
          <div><p className="font-semibold uppercase text-slate-500">Documento</p><p className="mt-0.5 text-slate-800">{orden.documento_numero || "-"}</p></div>
          <div><p className="font-semibold uppercase text-slate-500">Orden</p><p className="mt-0.5 text-slate-800">{orden.codigo || "-"}</p></div>
          <div><p className="font-semibold uppercase text-slate-500">Empresa</p><p className="mt-0.5 text-slate-800">{orden.empresa || "-"}</p></div>
          <div><p className="font-semibold uppercase text-slate-500">Puesto</p><p className="mt-0.5 text-slate-800">{orden.puesto_trabajo || "-"}</p></div>
          <div><p className="font-semibold uppercase text-slate-500">Evaluación</p><p className="mt-0.5 text-slate-800">{orden.tipo_nombre || orden.tipo_codigo || "-"}</p></div>
        </section>

        {mostrarSignosVitales ? (
          <section className="border-y border-slate-200 bg-white px-4 py-3 shadow-sm" aria-labelledby="signos-vitales-examen-title">
            <h2 id="signos-vitales-examen-title" className="mb-2 text-xs font-bold uppercase text-slate-600">Signos vitales de la evaluación</h2>
            <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 xl:grid-cols-8">
              {[
                ["Talla", triaje.talla_cm, "cm"],
                ["Peso", triaje.peso_kg, "kg"],
                ["IMC", triaje.imc, ""],
                ["Perímetro abdominal", triaje.perimetro_abdominal_cm, "cm"],
                ["F. respiratoria", triaje.frecuencia_respiratoria, "rpm"],
                ["F. cardiaca", triaje.frecuencia_cardiaca, "lpm"],
                ["Presión arterial", triaje.presion_sistolica || triaje.presion_diastolica ? `${triaje.presion_sistolica || "-"}/${triaje.presion_diastolica || "-"}` : "", "mmHg"],
                ["Temperatura", triaje.temperatura, "°C"],
              ].map(([label, value, unit]) => (
                <div key={label} className="border-l-2 border-cyan-300 bg-slate-50 px-2 py-1.5">
                  <p className="text-[10px] font-semibold uppercase text-slate-500">{label}</p>
                  <p className="mt-0.5 font-semibold text-slate-900">{String(value || "-")}{value && unit ? ` ${unit}` : ""}</p>
                </div>
              ))}
            </div>
            {Object.keys(triaje).length === 0 ? <p className="mt-2 text-xs text-amber-700">Triaje aún no finalizado para esta orden.</p> : null}
          </section>
        ) : null}

        {!esVistaEnfermeria && mostrarNavegacionPsicologia ? (
          <section className="border-y border-cyan-200 bg-white px-4 py-3 shadow-sm" aria-labelledby="psico-links-title">
            <h2 id="psico-links-title" className="mb-2 text-xs font-bold uppercase text-slate-600">Pruebas psicológicas relacionadas</h2>
            <div className="flex flex-wrap gap-2">
              {pruebasPsicologiaRelacionadas.length === 0 ? <p className="text-xs text-slate-500">No hay otras pruebas psicológicas asociadas en esta orden.</p> : null}
              {pruebasPsicologiaRelacionadas.map((exam) => {
                const activo = Number(exam.id) === Number(detalleId);
                return (
                  <Link
                    key={exam.id}
                    className={`inline-flex items-center gap-2 rounded border px-3 py-1.5 text-xs font-semibold ${activo ? "border-cyan-500 bg-cyan-50 text-cyan-900" : "border-slate-300 bg-white text-slate-700 hover:bg-slate-50"}`}
                    to={rutaExamen(baseEvaluacionesPath, ordenId, exam.id)}
                    aria-current={activo ? "page" : undefined}
                    title={exam.descripcion}
                  >
                    {exam.codigo}
                    <span className="hidden max-w-[300px] truncate sm:inline">{exam.descripcion}</span>
                  </Link>
                );
              })}
            </div>
          </section>
        ) : null}

        {readOnly ? <div className="border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">La orden está {orden.estado}. El examen se muestra en modo de solo lectura.</div> : null}
        {error ? <div className="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 print:hidden">{error}</div> : null}
        {message ? <div className="border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 print:hidden">{message}</div> : null}

        <div className="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_310px]">
          <section className="min-w-0 bg-white p-3 shadow-sm sm:p-5" aria-labelledby="formato-clinico-title">
            <div className="mb-4 flex items-center gap-2 border-b border-slate-200 pb-3">
              <FiFileText className="text-cyan-700" />
              <h2 id="formato-clinico-title" className="font-semibold text-slate-900">Formato clínico</h2>
              <span className="ml-auto text-xs text-slate-500">{resultadoContexto.detalle?.template_code || "general_basico"}</span>
            </div>
            <FormatoClinicoCampos
              templateCode={templateCode}
              datos={form.datos}
              onChange={cambiarDato}
              onAudiometriaChange={cambiarAudiometria}
              onParametroChange={cambiarParametro}
              disabled={readOnly || saving}
            />
            <label className="mt-4 block text-xs font-medium text-slate-700">
              <span className="mb-1 block">Observación clínica</span>
              <textarea className="min-h-24 w-full resize-y rounded border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500" value={form.observacion} onChange={(event) => setForm((current) => ({ ...current, observacion: event.target.value }))} disabled={readOnly || saving} />
            </label>
          </section>

          <aside className="space-y-4 print:hidden">
            <section className="bg-white p-4 shadow-sm">
              <h2 className="flex items-center gap-2 font-semibold text-slate-900"><FiUser className="text-cyan-700" />Responsable</h2>
              <label className="mt-3 block text-xs font-medium text-slate-700">
                <span className="mb-1 block">Médico/Psicólogo responsable</span>
                <select
                  className="h-10 w-full rounded border border-slate-300 px-2 text-sm"
                  value={responsableProfesionalId > 0 ? String(responsableProfesionalId) : "0"}
                  onChange={(event) => onChangeResponsableProfesional(event.target.value)}
                  disabled={readOnly || saving}
                >
                  <option value="0">Seleccionar responsable...</option>
                  {profesionales.map((profesional) => (
                    <option key={profesional.id} value={String(profesional.id)}>
                      {formatProfesionalName(profesional)} · {formatColegiatura(profesional)}
                    </option>
                  ))}
                </select>
              </label>
              <p className="mt-3 text-sm font-medium text-slate-800">{responsableNombreVisible}</p>
              <p className="text-xs text-slate-500">{responsableColegiaturaVisible}</p>
              {resultado?.updated_at ? <p className="mt-3 text-xs text-slate-500">Última actualización: {resultado.updated_at}</p> : null}
            </section>

            {!esVistaEnfermeria ? (
              <section className="space-y-3 bg-white p-4 shadow-sm">
                <h2 className="font-semibold text-slate-900">Plantillas</h2>
                <select className="h-10 w-full rounded border border-slate-300 px-2 text-sm" value={plantillaId} onChange={(event) => setPlantillaId(event.target.value)} disabled={readOnly || templateSaving}>
                  {plantillas.map((template) => <option key={`${template.id || 0}-${template.codigo || "template"}`} value={String(template.id || 0)}>{template.nombre || template.codigo || "Plantilla sugerida"}</option>)}
                </select>
                <button type="button" className="h-10 w-full rounded border border-cyan-300 text-sm font-medium text-cyan-700 hover:bg-cyan-50 disabled:opacity-50" onClick={aplicarPlantilla} disabled={readOnly || templateSaving || plantillas.length === 0}>Aplicar plantilla</button>
                <input className="h-10 w-full rounded border border-slate-300 px-3 text-sm" value={plantillaNombre} onChange={(event) => setPlantillaNombre(event.target.value)} placeholder="Nombre de plantilla" disabled={readOnly || templateSaving} />
                <div className="grid grid-cols-[1fr_40px] gap-2">
                  <button type="button" className="h-10 rounded border border-amber-300 text-sm font-medium text-amber-800 hover:bg-amber-50 disabled:opacity-50" onClick={guardarPlantilla} disabled={readOnly || templateSaving}>{templateSaving ? "Procesando..." : "Guardar plantilla"}</button>
                  <button type="button" className="flex h-10 items-center justify-center rounded border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50" onClick={eliminarPlantilla} disabled={readOnly || templateSaving || Number(plantillaId) <= 0} title="Eliminar plantilla" aria-label="Eliminar plantilla"><FiTrash2 /></button>
                </div>
              </section>
            ) : null}

            <section className="space-y-2 bg-white p-4 shadow-sm">
              <button type="button" className="flex h-11 w-full items-center justify-center gap-2 rounded border border-cyan-400 text-sm font-semibold text-cyan-800 hover:bg-cyan-50 disabled:opacity-50" onClick={() => guardar("borrador")} disabled={readOnly || saving}><FiSave />{saving ? "Guardando..." : "Guardar borrador"}</button>
              <button type="button" className="flex h-11 w-full items-center justify-center gap-2 rounded bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50" onClick={() => guardar("finalizado")} disabled={readOnly || saving}><FiCheckCircle />Finalizar examen</button>
              {!esVistaEnfermeria ? <button type="button" className="flex h-11 w-full items-center justify-center gap-2 rounded border border-slate-400 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50" onClick={imprimir} disabled={String(resultado?.estado || "") !== "finalizado"}><FiPrinter />Imprimir resultado</button> : null}
            </section>
          </aside>
        </div>
      </div>
    </main>
  );
}