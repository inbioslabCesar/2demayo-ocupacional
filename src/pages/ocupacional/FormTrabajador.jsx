import { useEffect, useMemo, useState } from "react";
import {
  actualizarBiometriaPacienteClinico,
  listarCatalogosLaboralesEmpresa,
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
  const [areas, setAreas] = useState([]);
  const [puestos, setPuestos] = useState([]);
  const [catalogosLoading, setCatalogosLoading] = useState(false);
  const [laborData, setLaborData] = useState(initialLaborData);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [serverMessage, setServerMessage] = useState("");
  const [serverError, setServerError] = useState("");
  const [bioSaving, setBioSaving] = useState(false);
  const [bioMessage, setBioMessage] = useState("");
  const [bioError, setBioError] = useState("");
  const [bioPayload, setBioPayload] = useState({
    firma_digital: "",
    huella_digital: "",
    fotografia: "",
  });

  const toDataUrl = (file) =>
    new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result || ""));
      reader.onerror = () => reject(new Error("No se pudo leer el archivo"));
      reader.readAsDataURL(file);
    });

  const onBioFileChange = (field) => async (event) => {
    const file = event.target.files?.[0];
    setBioError("");
    setBioMessage("");
    if (!file) {
      setBioPayload((prev) => ({ ...prev, [field]: "" }));
      return;
    }
    if (!/^image\/(png|jpeg|jpg)$/i.test(file.type)) {
      setBioError("Solo se permiten imagenes PNG o JPG para biometria.");
      event.target.value = "";
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      setBioError("Cada archivo biometrico debe pesar maximo 2MB.");
      event.target.value = "";
      return;
    }
    try {
      const dataUrl = await toDataUrl(file);
      setBioPayload((prev) => ({ ...prev, [field]: dataUrl }));
    } catch (error) {
      setBioError(error.message || "No se pudo procesar el archivo biometrico.");
    }
  };

  const guardarBiometria = async () => {
    if (!identidad?.id) {
      setBioError("Primero verifique la identidad.");
      return;
    }
    const hasChanges =
      !!bioPayload.firma_digital || !!bioPayload.huella_digital || !!bioPayload.fotografia;
    if (!hasChanges) {
      setBioError("Seleccione al menos un archivo: firma, huella o fotografia.");
      return;
    }

    setBioSaving(true);
    setBioError("");
    setBioMessage("");
    try {
      const data = await actualizarBiometriaPacienteClinico({
        patientId: identidad.id,
        firmaDigital: bioPayload.firma_digital || null,
        huellaDigital: bioPayload.huella_digital || null,
        fotografia: bioPayload.fotografia || null,
      });
      setIdentidad((prev) =>
        prev
          ? {
              ...prev,
              tiene_firma_digital: !!data?.tiene_firma_digital,
              tiene_huella_digital: !!data?.tiene_huella_digital,
              tiene_fotografia: !!data?.tiene_fotografia,
            }
          : prev
      );
      setBioPayload({ firma_digital: "", huella_digital: "", fotografia: "" });
      setBioMessage("Biometria actualizada en el paciente clinico.");
    } catch (error) {
      setBioError(error.message || "No se pudo guardar la biometria.");
    } finally {
      setBioSaving(false);
    }
  };

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

  useEffect(() => {
    let cancelled = false;
    const empresaId = Number(laborData.empresa_id || 0);
    if (!empresaId) {
      setAreas([]);
      setPuestos([]);
      return undefined;
    }

    setCatalogosLoading(true);
    Promise.all([
      listarCatalogosLaboralesEmpresa({ empresaId, tipo: "area", estado: "activo" }),
      listarCatalogosLaboralesEmpresa({ empresaId, tipo: "puesto", estado: "activo" }),
    ])
      .then(([areasData, puestosData]) => {
        if (!cancelled) {
          setAreas(Array.isArray(areasData) ? areasData : []);
          setPuestos(Array.isArray(puestosData) ? puestosData : []);
        }
      })
      .catch((error) => {
        if (!cancelled) {
          setAreas([]);
          setPuestos([]);
          setServerError(error.message || "No se pudieron cargar áreas y puestos de la empresa.");
        }
      })
      .finally(() => {
        if (!cancelled) setCatalogosLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [laborData.empresa_id]);

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
    setLaborData((prev) => field === "empresa_id"
      ? { ...prev, empresa_id: value, puesto_trabajo: "", area_riesgo: "" }
      : { ...prev, [field]: value });
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
      const registered = await registrarTrabajadorOcupacional({
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
      setBioPayload({ firma_digital: "", huella_digital: "", fotografia: "" });
      setErrors({});
      if (typeof onCreated === "function") {
        onCreated(registered);
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

      {identidad?.id ? (
        <div className="mt-4 rounded-lg border border-cyan-200 bg-cyan-50 p-4">
          <h3 className="text-sm font-semibold text-cyan-900">Biometria del paciente clinico</h3>
          <div className="mt-2 grid grid-cols-1 gap-2 text-sm text-cyan-900 md:grid-cols-3">
            <div className={`rounded-md border px-3 py-2 ${identidad?.tiene_firma_digital ? "border-emerald-300 bg-emerald-50" : "border-amber-300 bg-amber-50"}`}>
              Firma digital: {identidad?.tiene_firma_digital ? "Registrada" : "Pendiente"}
            </div>
            <div className={`rounded-md border px-3 py-2 ${identidad?.tiene_huella_digital ? "border-emerald-300 bg-emerald-50" : "border-amber-300 bg-amber-50"}`}>
              Huella digital: {identidad?.tiene_huella_digital ? "Registrada" : "Pendiente"}
            </div>
            <div className={`rounded-md border px-3 py-2 ${identidad?.tiene_fotografia ? "border-emerald-300 bg-emerald-50" : "border-amber-300 bg-amber-50"}`}>
              Fotografia: {identidad?.tiene_fotografia ? "Registrada" : "Pendiente"}
            </div>
          </div>

          <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-cyan-900">Cargar firma</label>
              <input type="file" accept="image/png,image/jpeg" onChange={onBioFileChange("firma_digital")} className="w-full rounded-md border border-cyan-200 bg-white px-2 py-1 text-xs" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-cyan-900">Cargar huella</label>
              <input type="file" accept="image/png,image/jpeg" onChange={onBioFileChange("huella_digital")} className="w-full rounded-md border border-cyan-200 bg-white px-2 py-1 text-xs" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-cyan-900">Cargar fotografia</label>
              <input type="file" accept="image/png,image/jpeg" onChange={onBioFileChange("fotografia")} className="w-full rounded-md border border-cyan-200 bg-white px-2 py-1 text-xs" />
            </div>
          </div>

          <div className="mt-3 flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={guardarBiometria}
              disabled={bioSaving || !identidad?.id}
              className="rounded-md bg-cyan-700 px-3 py-2 text-xs font-semibold text-white hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              {bioSaving ? "Guardando biometria..." : "Guardar biometria"}
            </button>
            {bioMessage ? <span className="text-xs font-medium text-emerald-700">{bioMessage}</span> : null}
            {bioError ? <span className="text-xs font-medium text-red-700">{bioError}</span> : null}
          </div>
        </div>
      ) : null}

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
          <select
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.puesto_trabajo}
            onChange={onLaborChange("puesto_trabajo")}
            disabled={!identidad?.id || !laborData.empresa_id || catalogosLoading}
          >
            <option value="">{catalogosLoading ? "Cargando puestos..." : "Seleccione puesto"}</option>
            {puestos.map((puesto) => <option key={puesto.id} value={puesto.nombre}>{puesto.nombre}</option>)}
          </select>
          {!catalogosLoading && laborData.empresa_id && puestos.length === 0 ? (
            <p className="mt-1 text-xs text-amber-700">Esta empresa no tiene puestos activos. Créelos desde Empresas &gt; Puesto.</p>
          ) : null}
          {errors.puesto_trabajo ? <p className="mt-1 text-xs text-red-600">{errors.puesto_trabajo}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Área</label>
          <select
            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            value={laborData.area_riesgo}
            onChange={onLaborChange("area_riesgo")}
            disabled={!identidad?.id || !laborData.empresa_id || catalogosLoading}
          >
            <option value="">{catalogosLoading ? "Cargando áreas..." : "Seleccione área"}</option>
            {areas.map((area) => <option key={area.id} value={area.nombre}>{area.nombre}</option>)}
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
