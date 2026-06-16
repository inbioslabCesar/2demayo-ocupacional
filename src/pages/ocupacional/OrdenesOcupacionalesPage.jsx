import { useCallback, useEffect, useMemo, useState } from "react";
import {
  listarEmpresasOcupacionales,
  listarProtocolosOcupacionales,
  listarTiposEvaluacionOcupacional,
  listarTrabajadoresOcupacionalesPaginado,
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
  registrarOrdenOcupacional,
} from "../../api/ocupacionalApi";

function todayIso() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  const d = String(now.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
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

  const [preview, setPreview] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [registrando, setRegistrando] = useState(false);

  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState({ page: 1, per_page: 20, total: 0, total_pages: 0 });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
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
        const [empData, tipoData] = await Promise.all([
          listarEmpresasOcupacionales({ estado: "activo" }),
          listarTiposEvaluacionOcupacional(),
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
  }, [empresaId, tipoEvaluacionId]);

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
    setLoading(true);
    setError("");
    try {
      const payload = await listarOrdenesOcupacionalesPaginado({
        empresaId,
        estado: filtroEstado,
        tipo: filtroTipo,
        fechaDesde: filtroFechaDesde,
        fechaHasta: filtroFechaHasta,
        q: qDebounced,
        page,
        perPage,
      });
      setRows(payload.data || []);
      setMeta(payload.meta || { page: 1, per_page: perPage, total: 0, total_pages: 0 });
    } catch (err) {
      setError(err.message || "No se pudo cargar ordenes");
    } finally {
      setLoading(false);
    }
  }, [empresaId, filtroEstado, filtroTipo, filtroFechaDesde, filtroFechaHasta, qDebounced, page, perPage]);

  useEffect(() => {
    cargarOrdenes();
  }, [cargarOrdenes]);

  const cargarResumen = useCallback(async () => {
    try {
      const data = await obtenerResumenOrdenesOcupacionales({
        empresaId,
        estado: filtroEstado,
        tipo: filtroTipo,
        fechaDesde: filtroFechaDesde,
        fechaHasta: filtroFechaHasta,
        q: qDebounced,
      });
      setResumen(data);
    } catch {
      setResumen(null);
    }
  }, [empresaId, filtroEstado, filtroTipo, filtroFechaDesde, filtroFechaHasta, qDebounced]);

  useEffect(() => {
    cargarResumen();
  }, [cargarResumen]);

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
      const data = await registrarOrdenOcupacional({
        empresaId,
        trabajadorId,
        protocoloId,
        tipoEvaluacionId,
        fechaOrden,
        observacion,
      });

      setMessage(`Orden registrada: ${data.codigo} (${data.total_items} examenes)`);
      setObservacion("");
      await cargarOrdenes();
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

      const jsPDF = (await import("jspdf")).default;
      const doc = new jsPDF();
      doc.setFontSize(16);
      doc.text("CERTIFICADO DE APTITUD OCUPACIONAL", 105, 20, { align: "center" });
      doc.setFontSize(11);
      doc.text(`Orden: ${det.codigo}`, 14, 34);
      doc.text(`Fecha: ${det.fecha_orden}`, 14, 41);
      doc.text(`Empresa: ${det.empresa}`, 14, 48);
      doc.text(`Documento: ${det.documento_numero}`, 14, 55);
      doc.text(`Puesto: ${det.puesto_trabajo}`, 14, 62);
      doc.text(`Protocolo: ${det.protocolo_descripcion}`, 14, 69);
      doc.text(`Tipo evaluacion: ${det.tipo_codigo} - ${det.tipo_nombre}`, 14, 76);

      doc.setFontSize(12);
      doc.text(`APTITUD FINAL: ${det.aptitud_final}`, 14, 92);
      doc.setFontSize(10);
      doc.text(`Restricciones: ${det.restriccion_final || "Ninguna"}`, 14, 102);
      doc.text(`Recomendaciones: ${det.recomendacion_final || "Ninguna"}`, 14, 110);
      doc.text(`Medico responsable: ${det.medico_responsable || "No consignado"}`, 14, 118);

      doc.text("Este certificado se emite en base al cierre formal de la orden ocupacional.", 14, 136);
      doc.text(`Emitido: ${new Date().toLocaleString()}`, 14, 144);

      const safeCode = String(det.codigo || `orden_${ordenId}`).replace(/[^A-Za-z0-9_-]/g, "_");
      doc.save(`certificado_aptitud_${safeCode}.pdf`);
      setMessage(`Certificado emitido: ${safeCode}`);
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
      await cargarOrdenes();
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
      await cargarOrdenes();
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
      await cargarOrdenes();
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
        empresaId,
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
        empresaId,
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

        <div className="mb-3 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-6">
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
                      >
                        {pdfId === r.id ? "PDF..." : "PDF"}
                      </button>
                      <button
                        type="button"
                        className="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        onClick={() => onEmitirCertificado(r.id)}
                        disabled={certificandoId === r.id || r.estado !== "cerrada" || !String(r.aptitud_final || "").trim()}
                      >
                        {certificandoId === r.id ? "Cert..." : "Certificado"}
                      </button>
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
                            <button
                              type="button"
                              className="rounded border border-cyan-300 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                              onClick={() => onGuardarDetalle(it.id)}
                              disabled={savingDetalleId === it.id || detalleModalData.estado === "anulada" || detalleModalData.estado === "cerrada"}
                            >
                              {savingDetalleId === it.id ? "Guardando..." : "Guardar"}
                            </button>
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
    </div>
  );
}
