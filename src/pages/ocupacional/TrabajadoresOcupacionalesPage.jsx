import { useCallback, useEffect, useState } from "react";
import {
  darBajaTrabajadorOcupacional,
  listarEmpresasOcupacionales,
  listarTrabajadoresOcupacionalesPaginado,
} from "../../api/ocupacionalApi";
import FormTrabajador from "./FormTrabajador";

export default function TrabajadoresOcupacionalesPage() {
  const [estado, setEstado] = useState("todos");
  const [empresaId, setEmpresaId] = useState(0);
  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [sortBy, setSortBy] = useState("fecha_ingreso");
  const [sortDir, setSortDir] = useState("desc");
  const [empresas, setEmpresas] = useState([]);
  const [meta, setMeta] = useState({ page: 1, per_page: 20, total: 0, total_pages: 0 });
  const [trabajadores, setTrabajadores] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const t = setTimeout(() => {
      setQDebounced(q.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(t);
  }, [q]);

  useEffect(() => {
    let cancelled = false;

    async function loadEmpresas() {
      try {
        const rows = await listarEmpresasOcupacionales({ estado: "todos" });
        if (!cancelled) {
          setEmpresas(rows || []);
        }
      } catch (_) {
        if (!cancelled) {
          setEmpresas([]);
        }
      }
    }

    loadEmpresas();

    return () => {
      cancelled = true;
    };
  }, []);

  const loadTrabajadores = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await listarTrabajadoresOcupacionalesPaginado({
        estado,
        empresaId,
        q: qDebounced,
        page,
        perPage,
        sortBy,
        sortDir,
      });
      setTrabajadores(payload.data || []);
      setMeta(payload.meta || { page: 1, per_page: perPage, total: 0, total_pages: 0 });
    } catch (err) {
      setError(err.message || "No se pudo cargar trabajadores");
    } finally {
      setLoading(false);
    }
  }, [estado, empresaId, qDebounced, page, perPage, sortBy, sortDir]);

  useEffect(() => {
    loadTrabajadores();
  }, [loadTrabajadores]);

  const onBaja = async (id) => {
    if (!window.confirm("¿Desea dar de baja a este trabajador?")) return;
    try {
      await darBajaTrabajadorOcupacional(id);
      await loadTrabajadores();
    } catch (err) {
      setError(err.message || "No se pudo dar de baja al trabajador");
    }
  };

  const handleCreated = () => {
    setPage(1);
    loadTrabajadores();
  };

  const totalPages = Number(meta.total_pages || 0);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Salud Ocupacional - Trabajadores</h1>
        <p className="text-sm text-slate-600 mt-1">Busque identidad en clinica y registre el contexto laboral por empresa.</p>
      </div>
      <FormTrabajador onCreated={handleCreated} />

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-3 space-y-2">
          <h2 className="text-lg font-semibold text-slate-900">Listado de Trabajadores</h2>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <input
              className="rounded-md border border-slate-300 px-3 py-2 text-sm sm:col-span-2 lg:col-span-1"
              placeholder="Buscar por documento, empresa o puesto"
              value={q}
              onChange={(e) => setQ(e.target.value)}
            />
            <select
              className="rounded-md border border-slate-300 px-3 py-2 text-sm"
              value={empresaId}
              onChange={(e) => {
                setEmpresaId(Number(e.target.value));
                setPage(1);
              }}
            >
              <option value={0}>Todas las empresas</option>
              {empresas.map((empresa) => (
                <option key={empresa.id} value={empresa.id}>
                  {empresa.razon_social}
                </option>
              ))}
            </select>
            <select
              className="rounded-md border border-slate-300 px-3 py-2 text-sm"
              value={estado}
              onChange={(e) => {
                setEstado(e.target.value);
                setPage(1);
              }}
            >
              <option value="todos">Todos</option>
              <option value="activo">Activos</option>
              <option value="retirado">Retirados</option>
            </select>
            <div className="flex gap-2">
              <select
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                value={sortBy}
                onChange={(e) => {
                  setSortBy(e.target.value);
                  setPage(1);
                }}
              >
                <option value="fecha_ingreso">Fecha ingreso</option>
                <option value="documento_numero">Documento</option>
                <option value="empresa">Empresa</option>
                <option value="created_at">Creación</option>
              </select>
              <select
                className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                value={sortDir}
                onChange={(e) => {
                  setSortDir(e.target.value);
                  setPage(1);
                }}
              >
                <option value="asc">↑</option>
                <option value="desc">↓</option>
              </select>
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
                <option value={100}>100</option>
              </select>
            </div>
          </div>
        </div>

        {loading ? <p className="text-sm text-slate-500">Cargando trabajadores...</p> : null}
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2 pr-3">Documento</th>
                <th className="py-2 pr-3">Empresa</th>
                <th className="py-2 pr-3">Puesto</th>
                <th className="py-2 pr-3">Estado</th>
                <th className="py-2 pr-3">Ingreso</th>
                <th className="py-2 pr-3">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {trabajadores.map((item) => (
                <tr key={item.id} className="border-b last:border-0">
                  <td className="py-2 pr-3">{item.documento_numero}</td>
                  <td className="py-2 pr-3">{item.empresa}</td>
                  <td className="py-2 pr-3">{item.puesto_trabajo}</td>
                  <td className="py-2 pr-3">
                    <span className={`rounded-full px-2 py-1 text-xs ${item.estado_laboral === "activo" ? "bg-emerald-100 text-emerald-700" : "bg-slate-200 text-slate-700"}`}>
                      {item.estado_laboral}
                    </span>
                  </td>
                  <td className="py-2 pr-3">{item.fecha_ingreso}</td>
                  <td className="py-2 pr-3">
                    {item.estado_laboral === "activo" ? (
                      <button
                        type="button"
                        onClick={() => onBaja(item.id)}
                        className="rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white hover:bg-rose-700"
                      >
                        Dar baja
                      </button>
                    ) : (
                      <span className="text-xs text-slate-400">Sin acciones</span>
                    )}
                  </td>
                </tr>
              ))}
              {!loading && trabajadores.length === 0 ? (
                <tr>
                  <td className="py-3 text-slate-500" colSpan={6}>No hay trabajadores para mostrar.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
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
            <span className="text-xs text-slate-600">Pág. {page}/{Math.max(1, totalPages)}</span>
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
    </div>
  );
}
