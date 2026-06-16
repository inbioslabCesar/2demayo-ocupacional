import { useEffect, useMemo, useState } from "react";
import { actualizarExamenOcupacional, crearExamenOcupacional } from "../../api/ocupacionalApi";

const initialForm = {
  codigo: "",
  descripcion: "",
  grupo: "",
  subgrupo: "",
  valores_normales: "",
  precio: "",
  posicion: "0",
};

function normalizeNumber(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function validateForm(form) {
  const errors = {};

  if (!String(form.codigo || "").trim()) {
    errors.codigo = "El codigo es obligatorio.";
  }

  if (!String(form.descripcion || "").trim()) {
    errors.descripcion = "La descripcion es obligatoria.";
  }

  const precio = Number(form.precio);
  if (!Number.isFinite(precio) || precio < 0) {
    errors.precio = "El precio debe ser numerico y mayor o igual a 0.";
  }

  const posicion = Number(form.posicion || 0);
  if (!Number.isFinite(posicion) || posicion < 0) {
    errors.posicion = "La posicion debe ser un numero mayor o igual a 0.";
  }

  return errors;
}

export default function FormExamenOcupacional({ editing, onSaved, onCancel }) {
  const [form, setForm] = useState(initialForm);
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [serverError, setServerError] = useState("");
  const [serverMessage, setServerMessage] = useState("");

  useEffect(() => {
    if (editing && typeof editing === "object") {
      setForm({
        codigo: String(editing.codigo || ""),
        descripcion: String(editing.descripcion || ""),
        grupo: String(editing.grupo || ""),
        subgrupo: String(editing.subgrupo || ""),
        valores_normales: String(editing.valores_normales || ""),
        precio: String(editing.precio ?? "0"),
        posicion: String(editing.posicion ?? "0"),
      });
      setErrors({});
      setServerError("");
      setServerMessage("");
    } else {
      setForm(initialForm);
      setErrors({});
      setServerError("");
      setServerMessage("");
    }
  }, [editing]);

  const isEditing = Boolean(editing && editing.id);

  const isValid = useMemo(() => Object.keys(validateForm(form)).length === 0, [form]);

  const onChange = (field) => (event) => {
    const value = event.target.value;
    setForm((prev) => ({ ...prev, [field]: value }));
    setServerError("");
    setServerMessage("");
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    const currentErrors = validateForm(form);
    setErrors(currentErrors);
    if (Object.keys(currentErrors).length > 0) {
      return;
    }

    setLoading(true);
    setServerError("");
    setServerMessage("");

    const payload = {
      codigo: String(form.codigo || "").trim().toUpperCase(),
      descripcion: String(form.descripcion || "").trim(),
      grupo: String(form.grupo || "").trim(),
      subgrupo: String(form.subgrupo || "").trim(),
      valores_normales: String(form.valores_normales || "").trim(),
      precio: normalizeNumber(form.precio),
      posicion: Math.trunc(normalizeNumber(form.posicion)),
    };

    try {
      if (isEditing) {
        await actualizarExamenOcupacional({ id: Number(editing.id), ...payload });
      } else {
        await crearExamenOcupacional(payload);
      }

      setServerMessage(isEditing ? "Examen actualizado correctamente." : "Examen creado correctamente.");

      if (!isEditing) {
        setForm(initialForm);
      }

      if (typeof onSaved === "function") {
        onSaved();
      }
    } catch (error) {
      setServerError(error.message || "No se pudo guardar el examen.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-slate-900">
        {isEditing ? "Editar Examen General" : "Nuevo Examen General"}
      </h2>
      <p className="mt-1 text-sm text-slate-600">
        Replica del maestro de examenes ocupacionales del sistema anterior.
      </p>

      <form className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" onSubmit={handleSubmit}>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Codigo *</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={form.codigo}
            onChange={onChange("codigo")}
            placeholder="EV_0001"
            maxLength={50}
          />
          {errors.codigo ? <p className="mt-1 text-xs text-red-600">{errors.codigo}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Descripcion *</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={form.descripcion}
            onChange={onChange("descripcion")}
            placeholder="EVALUACION MEDICA"
            maxLength={160}
          />
          {errors.descripcion ? <p className="mt-1 text-xs text-red-600">{errors.descripcion}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Grupo</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={form.grupo}
            onChange={onChange("grupo")}
            placeholder="MEDICINA"
            maxLength={100}
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Subgrupo</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={form.subgrupo}
            onChange={onChange("subgrupo")}
            placeholder="EVALUACION"
            maxLength={100}
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Precio *</label>
          <input
            type="number"
            min="0"
            step="0.01"
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={form.precio}
            onChange={onChange("precio")}
            placeholder="0.00"
          />
          {errors.precio ? <p className="mt-1 text-xs text-red-600">{errors.precio}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Posicion</label>
          <input
            type="number"
            min="0"
            step="1"
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={form.posicion}
            onChange={onChange("posicion")}
          />
          {errors.posicion ? <p className="mt-1 text-xs text-red-600">{errors.posicion}</p> : null}
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-medium text-slate-700">Valores Normales</label>
          <textarea
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            rows={3}
            value={form.valores_normales}
            onChange={onChange("valores_normales")}
            placeholder="Rango de referencia o descripcion de valores normales"
          />
        </div>

        {serverMessage ? <p className="md:col-span-2 text-sm text-emerald-600">{serverMessage}</p> : null}
        {serverError ? <p className="md:col-span-2 text-sm text-red-600">{serverError}</p> : null}

        <div className="md:col-span-2 flex flex-wrap justify-end gap-2">
          {isEditing ? (
            <button
              type="button"
              onClick={onCancel}
              className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Cancelar edicion
            </button>
          ) : null}
          <button
            type="submit"
            disabled={!isValid || loading}
            className="rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {loading ? "Guardando..." : isEditing ? "Guardar cambios" : "Guardar examen"}
          </button>
        </div>
      </form>
    </div>
  );
}
