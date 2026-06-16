import { useCallback, useEffect, useMemo, useState } from "react";
import {
  listarEmpresasOcupacionales,
  listarProtocolosOcupacionales,
  guardarProtocoloOcupacional,
  inactivarProtocoloOcupacional,
  listarMatrizProtocoloOcupacional,
  guardarMontoProtocoloOcupacional,
  listarPuestosOcupacionalesEmpresa,
  listarCondicionesProtocoloOcupacional,
  guardarCondicionProtocoloOcupacional,
  eliminarCondicionProtocoloOcupacional,
} from "../../api/ocupacionalApi";

function normalizeMoneyInput(value) {
  return String(value ?? "").replace(/[^0-9.,]/g, "").replace(',', '.');
}

export default function ProtocolosOcupacionalesPage() {
  const [empresas, setEmpresas] = useState([]);
  const [empresaId, setEmpresaId] = useState(0);

  const [protocolos, setProtocolos] = useState([]);
  const [protocoloId, setProtocoloId] = useState(0);
  const [descripcionProtocolo, setDescripcionProtocolo] = useState("");
  const [guardandoProtocolo, setGuardandoProtocolo] = useState(false);

  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);

  const [tipos, setTipos] = useState([]);
  const [rows, setRows] = useState([]);
  const [totales, setTotales] = useState({});
  const [meta, setMeta] = useState({ page: 1, per_page: 50, total: 0, total_pages: 0 });

  const [loading, setLoading] = useState(false);
  const [savingCellKey, setSavingCellKey] = useState("");
  const [error, setError] = useState("");

  const [puestosEmpresa, setPuestosEmpresa] = useState([]);
  const [condCatalogoSeleccionado, setCondCatalogoSeleccionado] = useState(null);
  const [condiciones, setCondiciones] = useState([]);
  const [condLoading, setCondLoading] = useState(false);
  const [condSaving, setCondSaving] = useState(false);
  const [condDeletingId, setCondDeletingId] = useState(0);
  const [condEditingId, setCondEditingId] = useState(0);
  const [condError, setCondError] = useState("");
  const [condForm, setCondForm] = useState({
    puesto_trabajo: "",
    sexo: "",
    edad_min: "",
    edad_max: "",
  });

  useEffect(() => {
    const t = window.setTimeout(() => {
      setQDebounced(q.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(t);
  }, [q]);

  const cargarEmpresas = useCallback(async () => {
    try {
      const data = await listarEmpresasOcupacionales({ estado: "activo" });
      setEmpresas(data);
      if (!empresaId && data.length > 0) {
        setEmpresaId(Number(data[0].id));
      }
    } catch (err) {
      setError(err.message || "No se pudo cargar empresas");
    }
  }, [empresaId]);

  useEffect(() => {
    cargarEmpresas();
  }, [cargarEmpresas]);

  const cargarProtocolos = useCallback(async () => {
    if (!empresaId) {
      setProtocolos([]);
      setProtocoloId(0);
      return;
    }

    try {
      const data = await listarProtocolosOcupacionales({ empresaId, estado: "activo" });
      setProtocolos(data);
      if (!data.find((p) => Number(p.id) === Number(protocoloId))) {
        setProtocoloId(data.length ? Number(data[0].id) : 0);
      }
    } catch (err) {
      setError(err.message || "No se pudo cargar protocolos");
    }
  }, [empresaId, protocoloId]);

  useEffect(() => {
    cargarProtocolos();
  }, [cargarProtocolos]);

  useEffect(() => {
    async function cargarPuestos() {
      if (!empresaId) {
        setPuestosEmpresa([]);
        return;
      }
      try {
        const data = await listarPuestosOcupacionalesEmpresa(empresaId);
        setPuestosEmpresa(data || []);
      } catch {
        setPuestosEmpresa([]);
      }
    }
    cargarPuestos();
  }, [empresaId]);

  const cargarMatriz = useCallback(async () => {
    if (!empresaId || !protocoloId) {
      setRows([]);
      setTipos([]);
      setTotales({});
      return;
    }

    setLoading(true);
    setError("");
    try {
      const payload = await listarMatrizProtocoloOcupacional({
        empresaId,
        protocoloId,
        q: qDebounced,
        page,
        perPage,
      });
      setTipos(payload.tipos || []);
      setRows(payload.data || []);
      setTotales(payload.totales || {});
      setMeta(payload.meta || { page: 1, per_page: perPage, total: 0, total_pages: 0 });
    } catch (err) {
      setError(err.message || "No se pudo cargar matriz de protocolo");
    } finally {
      setLoading(false);
    }
  }, [empresaId, protocoloId, qDebounced, page, perPage]);

  useEffect(() => {
    cargarMatriz();
  }, [cargarMatriz]);

  const empresaSeleccionada = useMemo(
    () => empresas.find((e) => Number(e.id) === Number(empresaId)) || null,
    [empresas, empresaId]
  );

  const protocoloSeleccionado = useMemo(
    () => protocolos.find((p) => Number(p.id) === Number(protocoloId)) || null,
    [protocolos, protocoloId]
  );

  const onGuardarProtocolo = async (e) => {
    e.preventDefault();
    if (!empresaId) {
      setError("Seleccione empresa");
      return;
    }
    if (!descripcionProtocolo.trim()) {
      setError("Ingrese descripcion del protocolo");
      return;
    }

    setGuardandoProtocolo(true);
    setError("");
    try {
      const data = await guardarProtocoloOcupacional({
        empresaId,
        descripcion: descripcionProtocolo.trim(),
      });
      setDescripcionProtocolo("");
      await cargarProtocolos();
      if (data?.id) {
        setProtocoloId(Number(data.id));
      }
    } catch (err) {
      setError(err.message || "No se pudo guardar protocolo");
    } finally {
      setGuardandoProtocolo(false);
    }
  };

  const onInactivarProtocolo = async () => {
    if (!protocoloId) return;
    if (!window.confirm("Desea inactivar este protocolo?")) return;

    try {
      await inactivarProtocoloOcupacional(protocoloId);
      await cargarProtocolos();
    } catch (err) {
      setError(err.message || "No se pudo inactivar protocolo");
    }
  };

  const onMontoBlur = async (row, tipoId, currentValue) => {
    if (!protocoloId) return;

    const monto = normalizeMoneyInput(currentValue);
    const key = `${row.catalogo_id}-${tipoId}`;

    setSavingCellKey(key);
    setError("");
    try {
      const saved = await guardarMontoProtocoloOcupacional({
        protocoloId,
        catalogoId: row.catalogo_id,
        tipoEvaluacionId: tipoId,
        monto,
      });

      setRows((prev) =>
        prev.map((r) => {
          if (Number(r.catalogo_id) !== Number(row.catalogo_id)) return r;
          return {
            ...r,
            montos: {
              ...r.montos,
              [String(tipoId)]: saved.monto || "",
            },
          };
        })
      );

      await cargarMatriz();
    } catch (err) {
      setError(err.message || "No se pudo guardar monto");
      await cargarMatriz();
    } finally {
      setSavingCellKey("");
    }
  };

  const cargarCondiciones = useCallback(async (catalogoId) => {
    if (!protocoloId || !catalogoId) {
      setCondiciones([]);
      return;
    }
    setCondLoading(true);
    setCondError("");
    try {
      const data = await listarCondicionesProtocoloOcupacional({
        protocoloId,
        catalogoId,
      });
      setCondiciones(data || []);
    } catch (err) {
      setCondError(err.message || "No se pudieron cargar condiciones");
    } finally {
      setCondLoading(false);
    }
  }, [protocoloId]);

  const abrirCondiciones = async (row) => {
    setCondCatalogoSeleccionado(row);
    setCondForm({ puesto_trabajo: "", sexo: "", edad_min: "", edad_max: "" });
    setCondEditingId(0);
    await cargarCondiciones(row.catalogo_id);
  };

  const onGuardarCondicion = async (e) => {
    e.preventDefault();
    if (!protocoloId || !condCatalogoSeleccionado?.catalogo_id) return;

    const puesto = String(condForm.puesto_trabajo || "").trim();
    const sexo = String(condForm.sexo || "").trim();
    const edadMin = String(condForm.edad_min || "").trim();
    const edadMax = String(condForm.edad_max || "").trim();

    if (!puesto && !sexo && !edadMin && !edadMax) {
      setCondError("Debe ingresar al menos un criterio: puesto, sexo o rango de edad");
      return;
    }

    setCondSaving(true);
    setCondError("");
    try {
      await guardarCondicionProtocoloOcupacional({
        id: condEditingId || undefined,
        protocoloId,
        catalogoId: condCatalogoSeleccionado.catalogo_id,
        puestoTrabajo: puesto,
        sexo,
        edadMin,
        edadMax,
      });
      setCondForm({ puesto_trabajo: "", sexo: "", edad_min: "", edad_max: "" });
      setCondEditingId(0);
      await cargarCondiciones(condCatalogoSeleccionado.catalogo_id);
    } catch (err) {
      setCondError(err.message || "No se pudo guardar condicion");
    } finally {
      setCondSaving(false);
    }
  };

  const onEditarCondicion = (condicion) => {
    setCondEditingId(Number(condicion.id));
    setCondForm({
      puesto_trabajo: condicion.puesto_trabajo || "",
      sexo: condicion.sexo || "",
      edad_min: condicion.edad_min ?? "",
      edad_max: condicion.edad_max ?? "",
    });
    setCondError("");
  };

  const onCancelarEdicionCondicion = () => {
    setCondEditingId(0);
    setCondForm({ puesto_trabajo: "", sexo: "", edad_min: "", edad_max: "" });
    setCondError("");
  };

  const onEliminarCondicion = async (id) => {
    if (!id) return;
    if (!window.confirm("Desea eliminar esta condicion?")) return;
    setCondDeletingId(Number(id));
    setCondError("");
    try {
      await eliminarCondicionProtocoloOcupacional(id);
      if (condCatalogoSeleccionado?.catalogo_id) {
        await cargarCondiciones(condCatalogoSeleccionado.catalogo_id);
      }
    } catch (err) {
      setCondError(err.message || "No se pudo eliminar condicion");
    } finally {
      setCondDeletingId(0);
    }
  };

  const totalPages = Number(meta.total_pages || 0);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Salud Ocupacional - Protocolos</h1>
        <p className="text-sm text-slate-600 mt-1">
          Defina protocolos por empresa y configure montos por examen segun tipo de evaluacion.
        </p>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div className="grid grid-cols-1 gap-2 lg:grid-cols-3">
          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={empresaId}
            onChange={(e) => {
              setEmpresaId(Number(e.target.value || 0));
              setPage(1);
            }}
          >
            <option value={0}>Seleccione empresa</option>
            {empresas.map((item) => (
              <option key={item.id} value={item.id}>
                {item.razon_social} ({item.ruc})
              </option>
            ))}
          </select>

          <select
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={protocoloId}
            onChange={(e) => {
              setProtocoloId(Number(e.target.value || 0));
              setPage(1);
            }}
            disabled={!empresaId}
          >
            <option value={0}>{protocolos.length ? "Seleccione protocolo" : "Sin protocolos"}</option>
            {protocolos.map((item) => (
              <option key={item.id} value={item.id}>
                {item.descripcion}
              </option>
            ))}
          </select>

          <button
            type="button"
            className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100 disabled:opacity-50"
            disabled={!protocoloId}
            onClick={onInactivarProtocolo}
          >
            Inactivar protocolo seleccionado
          </button>
        </div>

        <form onSubmit={onGuardarProtocolo} className="grid grid-cols-1 gap-2 lg:grid-cols-[1fr_auto]">
          <input
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            placeholder="Nuevo protocolo (ej. ADMINISTRATIVO, OPERATIVO, ALTURA...)"
            value={descripcionProtocolo}
            onChange={(e) => setDescripcionProtocolo(e.target.value)}
          />
          <button
            type="submit"
            disabled={guardandoProtocolo || !empresaId}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {guardandoProtocolo ? "Guardando..." : "Agregar protocolo"}
          </button>
        </form>

        {empresaSeleccionada ? (
          <p className="text-xs text-slate-600">
            Empresa: <span className="font-semibold">{empresaSeleccionada.razon_social}</span>
            {protocoloSeleccionado ? (
              <>
                {" "} | Protocolo: <span className="font-semibold">{protocoloSeleccionado.descripcion}</span>
              </>
            ) : null}
          </p>
        ) : null}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div className="grid grid-cols-1 gap-2 lg:grid-cols-3">
          <input
            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
            placeholder="Buscar examen por codigo, descripcion, grupo, subgrupo"
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
            <option value={20}>20</option>
            <option value={50}>50</option>
            <option value={100}>100</option>
            <option value={200}>200</option>
          </select>
        </div>

        {loading ? <p className="text-sm text-slate-500">Cargando matriz de protocolo...</p> : null}
        {error ? <p className="text-sm text-red-600">{error}</p> : null}

        <div className="overflow-x-auto hidden md:block">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2 pr-3">Codigo</th>
                <th className="py-2 pr-3">Descripcion</th>
                <th className="py-2 pr-3">Grupo</th>
                <th className="py-2 pr-3">Subgrupo</th>
                <th className="py-2 pr-3 text-center">Condiciones</th>
                {tipos.map((t) => (
                  <th key={t.id} className="py-2 pr-3 text-center">{t.codigo}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.catalogo_id} className="border-b last:border-0">
                  <td className="py-2 pr-3 font-semibold text-slate-700">{row.codigo}</td>
                  <td className="py-2 pr-3">{row.descripcion}</td>
                  <td className="py-2 pr-3">{row.grupo || "-"}</td>
                  <td className="py-2 pr-3">{row.subgrupo || "-"}</td>
                  <td className="py-2 pr-3 text-center">
                    <button
                      type="button"
                      className="rounded bg-violet-600 px-2 py-1 text-xs font-semibold text-white hover:bg-violet-700"
                      onClick={() => abrirCondiciones(row)}
                      disabled={!protocoloId}
                    >
                      Condiciones
                    </button>
                  </td>
                  {tipos.map((t) => {
                    const cellKey = `${row.catalogo_id}-${t.id}`;
                    const busy = savingCellKey === cellKey;
                    return (
                      <td key={t.id} className="py-2 pr-3">
                        <input
                          type="text"
                          className="w-28 rounded border border-slate-300 px-2 py-1 text-right"
                          defaultValue={row.montos?.[String(t.id)] ?? ""}
                          onBlur={(e) => onMontoBlur(row, t.id, e.target.value)}
                          disabled={!protocoloId || busy}
                        />
                      </td>
                    );
                  })}
                </tr>
              ))}
              {!loading && rows.length === 0 ? (
                <tr>
                  <td className="py-3 text-slate-500" colSpan={5 + tipos.length}>No hay examenes activos en catalogo para esta empresa.</td>
                </tr>
              ) : null}
            </tbody>
            {tipos.length > 0 ? (
              <tfoot>
                <tr className="border-t bg-slate-50 font-semibold text-slate-700">
                  <td className="py-2 pr-3" colSpan={5}>TOTAL</td>
                  {tipos.map((t) => (
                    <td key={t.id} className="py-2 pr-3 text-right">S/ {Number(totales?.[String(t.id)] || 0).toFixed(2)}</td>
                  ))}
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>

        <div className="md:hidden space-y-3">
          {rows.map((row) => (
            <div key={row.catalogo_id} className="rounded-lg border border-slate-200 p-3 space-y-2">
              <div>
                <p className="text-xs text-slate-500">{row.codigo}</p>
                <p className="text-sm font-semibold text-slate-800">{row.descripcion}</p>
              </div>
              <div className="grid grid-cols-2 gap-2 text-xs text-slate-600">
                <p><span className="font-semibold">Grupo:</span> {row.grupo || "-"}</p>
                <p><span className="font-semibold">Subgrupo:</span> {row.subgrupo || "-"}</p>
              </div>
              <button
                type="button"
                className="rounded bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"
                onClick={() => abrirCondiciones(row)}
                disabled={!protocoloId}
              >
                Condiciones
              </button>
              <div className="space-y-2">
                {tipos.map((t) => {
                  const cellKey = `${row.catalogo_id}-${t.id}`;
                  const busy = savingCellKey === cellKey;
                  return (
                    <label key={t.id} className="flex items-center justify-between gap-2 text-xs text-slate-700">
                      <span className="font-semibold">{t.codigo}</span>
                      <input
                        type="text"
                        className="w-28 rounded border border-slate-300 px-2 py-1 text-right"
                        defaultValue={row.montos?.[String(t.id)] ?? ""}
                        onBlur={(e) => onMontoBlur(row, t.id, e.target.value)}
                        disabled={!protocoloId || busy}
                      />
                    </label>
                  );
                })}
              </div>
            </div>
          ))}
          {!loading && rows.length === 0 ? (
            <p className="text-sm text-slate-500">No hay examenes activos en catalogo para esta empresa.</p>
          ) : null}
        </div>

        <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
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

      {condCatalogoSeleccionado ? (
        <div className="rounded-xl border border-violet-200 bg-white p-4 shadow-sm space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-lg font-semibold text-violet-900">
              Condiciones - {condCatalogoSeleccionado.codigo} - {condCatalogoSeleccionado.descripcion}
            </h2>
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-1 text-xs"
              onClick={() => {
                setCondCatalogoSeleccionado(null);
                setCondiciones([]);
                setCondError("");
                setCondEditingId(0);
              }}
            >
              Cerrar
            </button>
          </div>

          <form onSubmit={onGuardarCondicion} className="grid grid-cols-1 gap-2 md:grid-cols-5">
            <input
              list="puestos-empresa"
              className="rounded-md border border-slate-300 px-3 py-2 text-sm md:col-span-2"
              placeholder="Puesto (opcional)"
              value={condForm.puesto_trabajo}
              onChange={(e) => setCondForm((prev) => ({ ...prev, puesto_trabajo: e.target.value }))}
            />
            <datalist id="puestos-empresa">
              {puestosEmpresa.map((p) => (
                <option key={p.puesto_trabajo} value={p.puesto_trabajo} />
              ))}
            </datalist>

            <select
              className="rounded-md border border-slate-300 px-3 py-2 text-sm"
              value={condForm.sexo}
              onChange={(e) => setCondForm((prev) => ({ ...prev, sexo: e.target.value }))}
            >
              <option value="">Sexo (todos)</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>

            <input
              type="number"
              min={0}
              max={120}
              className="rounded-md border border-slate-300 px-3 py-2 text-sm"
              placeholder="Edad min"
              value={condForm.edad_min}
              onChange={(e) => setCondForm((prev) => ({ ...prev, edad_min: e.target.value }))}
            />

            <input
              type="number"
              min={0}
              max={120}
              className="rounded-md border border-slate-300 px-3 py-2 text-sm"
              placeholder="Edad max"
              value={condForm.edad_max}
              onChange={(e) => setCondForm((prev) => ({ ...prev, edad_max: e.target.value }))}
            />

            <div className="md:col-span-5">
              <button
                type="submit"
                disabled={condSaving}
                className="rounded bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
              >
                {condSaving ? "Guardando..." : condEditingId ? "Actualizar condicion" : "Agregar condicion"}
              </button>
              {condEditingId ? (
                <button
                  type="button"
                  className="ml-2 rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                  onClick={onCancelarEdicionCondicion}
                >
                  Cancelar edicion
                </button>
              ) : null}
            </div>
          </form>

          {condLoading ? <p className="text-sm text-slate-500">Cargando condiciones...</p> : null}
          {condError ? <p className="text-sm text-red-600">{condError}</p> : null}

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b text-left text-slate-500">
                  <th className="py-2 pr-3">Puesto</th>
                  <th className="py-2 pr-3">Sexo</th>
                  <th className="py-2 pr-3">Edad min</th>
                  <th className="py-2 pr-3">Edad max</th>
                  <th className="py-2 pr-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {condiciones.map((c) => (
                  <tr key={c.id} className="border-b last:border-0">
                    <td className="py-2 pr-3">{c.puesto_trabajo || "TODOS"}</td>
                    <td className="py-2 pr-3">{c.sexo === "M" ? "MASCULINO" : c.sexo === "F" ? "FEMENINO" : "TODOS"}</td>
                    <td className="py-2 pr-3">{c.edad_min ?? "-"}</td>
                    <td className="py-2 pr-3">{c.edad_max ?? "-"}</td>
                    <td className="py-2 pr-3">
                      <button
                        type="button"
                        className="mr-2 rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700"
                        onClick={() => onEditarCondicion(c)}
                      >
                        Editar
                      </button>
                      <button
                        type="button"
                        className="rounded bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                        onClick={() => onEliminarCondicion(c.id)}
                        disabled={condDeletingId === Number(c.id)}
                      >
                        {condDeletingId === Number(c.id) ? "Eliminando..." : "Eliminar"}
                      </button>
                    </td>
                  </tr>
                ))}
                {!condLoading && condiciones.length === 0 ? (
                  <tr>
                    <td className="py-3 text-slate-500" colSpan={5}>No hay condiciones registradas para este examen.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </div>
  );
}
