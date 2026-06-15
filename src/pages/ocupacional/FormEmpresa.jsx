import { useMemo, useState } from "react";
import { crearEmpresaOcupacional } from "../../api/ocupacionalApi";

const initialForm = {
  ruc: "",
  razon_social: "",
  direccion: "",
  telefono: "",
  correo: "",
};

function validateEmpresa(form) {
  const errors = {};

  if (!/^[0-9]{11}$/.test(form.ruc.trim())) {
    errors.ruc = "El RUC debe tener 11 digitos numericos.";
  }

  if (!form.razon_social.trim()) {
    errors.razon_social = "La razon social es obligatoria.";
  }

  if (form.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo.trim())) {
    errors.correo = "Ingrese un correo valido.";
  }

  return errors;
}

export default function FormEmpresa({ onCreated }) {
  const [form, setForm] = useState(initialForm);
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [serverMessage, setServerMessage] = useState("");
  const [serverError, setServerError] = useState("");

  const isValid = useMemo(() => Object.keys(validateEmpresa(form)).length === 0, [form]);

  const onChange = (field) => (event) => {
    const value = event.target.value;
    setForm((prev) => ({ ...prev, [field]: value }));
    setServerMessage("");
    setServerError("");
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    const currentErrors = validateEmpresa(form);
    setErrors(currentErrors);
    if (Object.keys(currentErrors).length > 0) {
      return;
    }

    setLoading(true);
    setServerMessage("");
    setServerError("");

    try {
      await crearEmpresaOcupacional({
        ruc: form.ruc.trim(),
        razon_social: form.razon_social.trim(),
        direccion: form.direccion.trim(),
        telefono: form.telefono.trim(),
        correo: form.correo.trim(),
      });

      setServerMessage("Empresa registrada correctamente.");
      setForm(initialForm);
      setErrors({});
      if (typeof onCreated === "function") {
        onCreated();
      }
    } catch (error) {
      setServerError(error.message || "No se pudo registrar la empresa.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-slate-900">Registro de Empresa Ocupacional</h2>
      <p className="mt-1 text-sm text-slate-600">Complete los datos para crear una empresa en Salud Ocupacional.</p>

      <form className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" onSubmit={onSubmit}>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">RUC *</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
            value={form.ruc}
            onChange={onChange("ruc")}
            maxLength={11}
            inputMode="numeric"
            placeholder="20123456789"
          />
          {errors.ruc ? <p className="mt-1 text-xs text-red-600">{errors.ruc}</p> : null}
        </div>

        <div className="md:col-span-1">
          <label className="mb-1 block text-sm font-medium text-slate-700">Razon social *</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
            value={form.razon_social}
            onChange={onChange("razon_social")}
            placeholder="Empresa SAC"
          />
          {errors.razon_social ? <p className="mt-1 text-xs text-red-600">{errors.razon_social}</p> : null}
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-medium text-slate-700">Direccion</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
            value={form.direccion}
            onChange={onChange("direccion")}
            placeholder="Av. Principal 123"
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Telefono</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
            value={form.telefono}
            onChange={onChange("telefono")}
            placeholder="+51 999 999 999"
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Correo</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
            value={form.correo}
            onChange={onChange("correo")}
            placeholder="contacto@empresa.com"
          />
          {errors.correo ? <p className="mt-1 text-xs text-red-600">{errors.correo}</p> : null}
        </div>

        {serverMessage ? <p className="md:col-span-2 text-sm text-emerald-600">{serverMessage}</p> : null}
        {serverError ? <p className="md:col-span-2 text-sm text-red-600">{serverError}</p> : null}

        <div className="md:col-span-2 flex justify-end">
          <button
            type="submit"
            disabled={!isValid || loading}
            className="rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {loading ? "Guardando..." : "Guardar Empresa"}
          </button>
        </div>
      </form>
    </div>
  );
}
