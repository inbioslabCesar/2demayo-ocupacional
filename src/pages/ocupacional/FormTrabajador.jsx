import { useEffect, useMemo, useState } from "react";
import {
  listarEmpresasOcupacionales,
  registrarTrabajadorOcupacional,
  verificarIdentidadClinica,
} from "../../api/ocupacionalApi";

const initialLaborData = {
  empresa_id: "",
  puesto_trabajo: "",
  area_riesgo: "",
  tipo_contrato: "",
  fecha_ingreso: "",
};

function validarDocumento(valor) {
  return /^[A-Za-z0-9]{6,15}$/.test((valor || "").trim());
}

function validarForm(identidad, laborData) {
  const errors = {};

  if (!identidad?.id) {
    errors.identidad = "Primero verifique la identidad del trabajador.";
  }
  if (!laborData.empresa_id) {
    errors.empresa_id = "Seleccione una empresa.";
  }
  if (!laborData.puesto_trabajo.trim()) {
    errors.puesto_trabajo = "El puesto de trabajo es obligatorio.";
  }
  if (!laborData.fecha_ingreso) {
    errors.fecha_ingreso = "La fecha de ingreso es obligatoria.";
  }

  return errors;
}

export default function FormTrabajador({ onCreated }) {
  const [documentoTipo, setDocumentoTipo] = useState("DNI");
  const [documentoNumero, setDocumentoNumero] = useState("");
  const [verificando, setVerificando] = useState(false);
  const [identidad, setIdentidad] = useState(null);
  const [identidadError, setIdentidadError] = useState("");

  const [empresas, setEmpresas] = useState([]);
  const [laborData, setLaborData] = useState(initialLaborData);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [serverMessage, setServerMessage] = useState("");
  const [serverError, setServerError] = useState("");

  useEffect(() => {
    let cancelled = false;

    async function loadEmpresas() {
      try {
        const data = await listarEmpresasOcupacionales({ estado: "activo" });
        if (!cancelled) {
          setEmpresas(data);
        }
      } catch (error) {
        if (!cancelled) {
          setServerError(error.message || "No se pudo cargar empresas.");
        }
      }
    }

    loadEmpresas();

    return () => {
      cancelled = true;
    };
  }, []);

  const canVerify = useMemo(() => validarDocumento(documentoNumero), [documentoNumero]);
  const canSubmit = useMemo(() => identidad?.id && !saving, [identidad, saving]);

  const onVerify = async () => {
    setIdentidad(null);
    setIdentidadError("");
    setServerError("");
    setServerMessage("");

    if (!canVerify) {
      setIdentidadError("Documento invalido. Use formato alfanumerico de 6 a 15 caracteres.");
      return;
    }

    setVerificando(true);
    try {
      const data = await verificarIdentidadClinica({
        documentoTipo,
        documentoNumero: documentoNumero.trim().toUpperCase(),
      });
      setIdentidad(data);
    } catch (error) {
      setIdentidadError(error.message || "No se encontro identidad para el documento.");
    } finally {
      setVerificando(false);
    }
  };

  const onLaborChange = (field) => (event) => {
    const value = event.target.value;
    setLaborData((prev) => ({ ...prev, [field]: value }));
    setServerError("");
    setServerMessage("");
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    const currentErrors = validarForm(identidad, laborData);
    setErrors(currentErrors);
    if (Object.keys(currentErrors).length > 0) {
      return;
    }

    setSaving(true);
    setServerMessage("");
    setServerError("");

    try {
      await registrarTrabajadorOcupacional({
        empresa_id: Number(laborData.empresa_id),
        external_patient_id: Number(identidad.id),
        documento_tipo: identidad.documento_tipo || documentoTipo,
        documento_numero: (identidad.documento_numero || documentoNumero).toUpperCase(),
        puesto_trabajo: laborData.puesto_trabajo.trim(),
        area_riesgo: laborData.area_riesgo,
        tipo_contrato: laborData.tipo_contrato,
        fecha_ingreso: laborData.fecha_ingreso,
        estado_laboral: "activo",
      });

      setServerMessage("Trabajador registrado correctamente.");
      setLaborData(initialLaborData);
      setIdentidad(null);
      setDocumentoNumero("");
      setErrors({});
      if (typeof onCreated === "function") {
        onCreated();
      }
    } catch (error) {
      setServerError(error.message || "No se pudo registrar el trabajador.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-slate-900">Registro de Trabajador Ocupacional</h2>
      <p className="mt-1 text-sm text-slate-600">Verifique identidad desde el sistema clinico y complete los datos laborales.</p>

      <div className="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Tipo documento</label>
          <select
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={documentoTipo}
            onChange={(event) => setDocumentoTipo(event.target.value)}
          >
            <option value="DNI">DNI</option>
            <option value="PASAPORTE">Pasaporte</option>
            <option value="CE">Carnet de Extranjeria</option>
          </select>
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-medium text-slate-700">Numero de documento</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={documentoNumero}
            onChange={(event) => setDocumentoNumero(event.target.value)}
            placeholder="Ingrese documento"
          />
        </div>

        <div className="flex items-end">
          <button
            type="button"
            onClick={onVerify}
            disabled={!canVerify || verificando}
            className="w-full rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {verificando ? "Verificando..." : "Verificar"}
          </button>
        </div>
      </div>

      {identidadError ? <p className="mt-2 text-sm text-red-600">{identidadError}</p> : null}

      <div className="mt-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Nombres</label>
          <input className="w-full rounded-md border border-slate-300 bg-slate-100 px-3 py-2 text-sm" value={identidad?.nombre || ""} readOnly />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Apellidos</label>
          <input className="w-full rounded-md border border-slate-300 bg-slate-100 px-3 py-2 text-sm" value={identidad?.apellidos || ""} readOnly />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Sexo</label>
          <input className="w-full rounded-md border border-slate-300 bg-slate-100 px-3 py-2 text-sm" value={identidad?.sexo || ""} readOnly />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Fecha de nacimiento</label>
          <input className="w-full rounded-md border border-slate-300 bg-slate-100 px-3 py-2 text-sm" value={identidad?.fecha_nacimiento || ""} readOnly />
        </div>
      </div>

      <form className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" onSubmit={onSubmit}>
        {errors.identidad ? <p className="md:col-span-2 text-sm text-red-600">{errors.identidad}</p> : null}

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Empresa *</label>
          <select
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.empresa_id}
            onChange={onLaborChange("empresa_id")}
            disabled={!identidad?.id}
          >
            <option value="">Seleccione empresa</option>
            {empresas.map((empresa) => (
              <option key={empresa.id} value={empresa.id}>
                {empresa.razon_social}
              </option>
            ))}
          </select>
          {errors.empresa_id ? <p className="mt-1 text-xs text-red-600">{errors.empresa_id}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Puesto de trabajo *</label>
          <input
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.puesto_trabajo}
            onChange={onLaborChange("puesto_trabajo")}
            disabled={!identidad?.id}
            placeholder="Operario, tecnico, supervisor..."
          />
          {errors.puesto_trabajo ? <p className="mt-1 text-xs text-red-600">{errors.puesto_trabajo}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Area de riesgo</label>
          <select
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.area_riesgo}
            onChange={onLaborChange("area_riesgo")}
            disabled={!identidad?.id}
          >
            <option value="">Seleccione</option>
            <option value="bajo">Bajo</option>
            <option value="medio">Medio</option>
            <option value="alto">Alto</option>
          </select>
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Tipo de contrato</label>
          <select
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.tipo_contrato}
            onChange={onLaborChange("tipo_contrato")}
            disabled={!identidad?.id}
          >
            <option value="">Seleccione</option>
            <option value="indefinido">Indefinido</option>
            <option value="plazo_fijo">Plazo fijo</option>
            <option value="temporal">Temporal</option>
          </select>
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Fecha de ingreso *</label>
          <input
            type="date"
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.fecha_ingreso}
            onChange={onLaborChange("fecha_ingreso")}
            disabled={!identidad?.id}
          />
          {errors.fecha_ingreso ? <p className="mt-1 text-xs text-red-600">{errors.fecha_ingreso}</p> : null}
        </div>

        {serverMessage ? <p className="md:col-span-2 text-sm text-emerald-600">{serverMessage}</p> : null}
        {serverError ? <p className="md:col-span-2 text-sm text-red-600">{serverError}</p> : null}

        <div className="md:col-span-2 flex justify-end">
          <button
            type="submit"
            disabled={!canSubmit}
            className="rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {saving ? "Guardando..." : "Registrar trabajador"}
          </button>
        </div>
      </form>
    </div>
  );
}
