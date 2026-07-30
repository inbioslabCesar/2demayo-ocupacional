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

const RESULTADO_ESTADOS = {
  pendiente: { label: "Sin atender", icon: FiAlertCircle, className: "border-red-200 bg-red-50 text-red-700" },
  borrador: { label: "En proceso", icon: FiClock, className: "border-amber-200 bg-amber-50 text-amber-800" },
  finalizado: { label: "Finalizado", icon: FiCheckCircle, className: "border-emerald-200 bg-emerald-50 text-emerald-700" },
  observado: { label: "Observado", icon: FiAlertCircle, className: "border-rose-300 bg-rose-100 text-rose-800" },
};

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
      window.print();
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
              <p className="mt-3 text-sm font-medium text-slate-800">{orden.medico_nombre_snapshot || orden.medico_responsable || "No asignado"}</p>
              <p className="text-xs text-slate-500">{orden.medico_cmp_snapshot ? `CMP ${orden.medico_cmp_snapshot}` : "Sin CMP registrado"}</p>
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