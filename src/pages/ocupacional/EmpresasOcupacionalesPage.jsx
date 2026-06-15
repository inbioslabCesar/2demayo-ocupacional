import { useCallback, useEffect, useState } from "react";
import { inactivarEmpresaOcupacional, listarEmpresasOcupacionalesPaginado } from "../../api/ocupacionalApi";
import FormEmpresa from "./FormEmpresa";

export default function EmpresasOcupacionalesPage() {
  const [estado, setEstado] = useState("todos");
  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [sortBy, setSortBy] = useState("razon_social");
  const [sortDir, setSortDir] = useState("asc");
  const [meta, setMeta] = useState({ page: 1, per_page: 20, total: 0, total_pages: 0 });
  const [empresas, setEmpresas] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const t = setTimeout(() => {
      setQDebounced(q.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(t);
  }, [q]);

  const loadEmpresas = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await listarEmpresasOcupacionalesPaginado({
        estado,
        q: qDebounced,
        page,
        perPage,
        sortBy,
        sortDir,
      });
      setEmpresas(payload.data || []);
      setMeta(payload.meta || { page: 1, per_page: perPage, total: 0, total_pages: 0 });
    } catch (err) {
      setError(err.message || "No se pudo cargar empresas");
    } finally {
      setLoading(false);
    }
  }, [estado, qDebounced, page, perPage, sortBy, sortDir]);

  useEffect(() => {
    loadEmpresas();
  }, [loadEmpresas]);

  const onInactivar = async (id) => {
    if (!window.confirm("¿Desea inactivar esta empresa?")) return;
    try {
      await inactivarEmpresaOcupacional(id);
      await loadEmpresas();
    } catch (err) {
      setError(err.message || "No se pudo inactivar empresa");
    }
  };

  const handleCreated = () => {
    setPage(1);
    loadEmpresas();
  };

  const totalPages = Number(meta.total_pages || 0);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Salud Ocupacional - Empresas</h1>
        <p className="text-sm text-slate-600 mt-1">Administre las empresas del subdominio ocupacional.</p>
      </div>
      <FormEmpresa onCreated={handleCreated} />

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-3 space-y-2">
          <h2 className="text-lg font-semibold text-slate-900">Listado de Empresas</h2>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <input
              className="rounded-md border border-slate-300 px-3 py-2 text-sm sm:col-span-2 lg:col-span-1"
              placeholder="Buscar por RUC o razon social"
              value={q}
              onChange={(e) => setQ(e.target.value)}
            />
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
              <option value="inactivo">Inactivos</option>
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
                <option value="razon_social">Razón social</option>
                <option value="ruc">RUC</option>
                <option value="estado">Estado</option>
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

        {loading ? <p className="text-sm text-slate-500">Cargando empresas...</p> : null}
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2 pr-3">RUC</th>
                <th className="py-2 pr-3">Razón social</th>
                <th className="py-2 pr-3">Estado</th>
                <th className="py-2 pr-3">Creado</th>
                <th className="py-2 pr-3">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {empresas.map((item) => (
                <tr key={item.id} className="border-b last:border-0">
                  <td className="py-2 pr-3">{item.ruc}</td>
                  <td className="py-2 pr-3">{item.razon_social}</td>
                  <td className="py-2 pr-3">
                    <span className={`rounded-full px-2 py-1 text-xs ${item.estado === "activo" ? "bg-emerald-100 text-emerald-700" : "bg-slate-200 text-slate-700"}`}>
                      {item.estado}
                    </span>
                  </td>
                  <td className="py-2 pr-3">{item.created_at || "-"}</td>
                  <td className="py-2 pr-3">
                    {item.estado === "activo" ? (
                      <button
                        type="button"
                        onClick={() => onInactivar(item.id)}
                        className="rounded bg-amber-500 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-600"
                      >
                        Inactivar
                      </button>
                    ) : (
                      <span className="text-xs text-slate-400">Sin acciones</span>
                    )}
                  </td>
                </tr>
              ))}
              {!loading && empresas.length === 0 ? (
                <tr>
                  <td className="py-3 text-slate-500" colSpan={5}>No hay empresas para mostrar.</td>
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
