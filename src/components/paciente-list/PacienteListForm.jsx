import React, { useState, useRef, useEffect } from "react";
import { BASE_URL } from "../../config/config";
import Swal from "sweetalert2";
import withReactContent from "sweetalert2-react-content";
import { authFetch } from "../../utils/apiClient";
import DatosBasicos from "./DatosBasicos.jsx";
import DatosEdad from "./DatosEdad.jsx";
import DatosAdicionales from "./DatosAdicionales.jsx";
import DatosContacto from "./DatosContacto.jsx";

function PacienteListForm({ initialData = {}, onRegistroExitoso, guardarPaciente }) {
  const MySwal = withReactContent(Swal);
  const [form, setForm] = useState({
    id: initialData.id || undefined,
    dni: initialData.dni || "",
    tipo_documento: initialData.tipo_documento || "dni",
    nombre: initialData.nombre || "",
    apellido: initialData.apellido || "",
    historia_clinica: initialData.historia_clinica || "",
    fecha_nacimiento: initialData.fecha_nacimiento || "",
    edad: initialData.edad || "",
    edad_unidad: initialData.edad_unidad || "años",
    procedencia: initialData.procedencia || "",
    tipo_seguro: initialData.tipo_seguro || "",
    sexo: initialData.sexo || "M",
    direccion: initialData.direccion || "",
    lugarnacimiento: initialData.lugarnacimiento || "",
    calle: initialData.calle || "",
    urbanizacion: initialData.urbanizacion || "",
    ocupacion: initialData.ocupacion || "",
    hijos: initialData.hijos ?? "",
    hijosdependientes: initialData.hijosdependientes ?? "",
    departamento: initialData.departamento || "",
    provincia: initialData.provincia || "",
    distrito: initialData.distrito || "",
    gradoinstruccion: initialData.gradoinstruccion || "",
    estadocivil: initialData.estadocivil || "",
    nombrepadre: initialData.nombrepadre || "",
    nombremadre: initialData.nombremadre || "",
    acompanante: initialData.acompanante || "",
    trabajoresidencia: initialData.trabajoresidencia ?? "",
    tiemporesidencia: initialData.tiemporesidencia ?? "",
    telefono: initialData.telefono || "",
    celular: initialData.celular || "",
    email: initialData.email || "",
    firma_digital: initialData.firma_digital || "",
    huella_digital: initialData.huella_digital || "",
    fotografia: initialData.fotografia || "",
  });

  useEffect(() => {
    setForm({
      id: initialData.id || undefined,
      dni: initialData.dni || "",
      tipo_documento: initialData.tipo_documento || "dni",
      nombre: initialData.nombre || "",
      apellido: initialData.apellido || "",
      historia_clinica: initialData.historia_clinica || "",
      fecha_nacimiento: initialData.fecha_nacimiento || "",
      edad: initialData.edad || "",
      edad_unidad: initialData.edad_unidad || "años",
      procedencia: initialData.procedencia || "",
      tipo_seguro: initialData.tipo_seguro || "",
      sexo: initialData.sexo || "M",
      direccion: initialData.direccion || "",
      lugarnacimiento: initialData.lugarnacimiento || "",
      calle: initialData.calle || "",
      urbanizacion: initialData.urbanizacion || "",
      ocupacion: initialData.ocupacion || "",
      hijos: initialData.hijos ?? "",
      hijosdependientes: initialData.hijosdependientes ?? "",
      departamento: initialData.departamento || "",
      provincia: initialData.provincia || "",
      distrito: initialData.distrito || "",
      gradoinstruccion: initialData.gradoinstruccion || "",
      estadocivil: initialData.estadocivil || "",
      nombrepadre: initialData.nombrepadre || "",
      nombremadre: initialData.nombremadre || "",
      acompanante: initialData.acompanante || "",
      trabajoresidencia: initialData.trabajoresidencia ?? "",
      tiemporesidencia: initialData.tiemporesidencia ?? "",
      telefono: initialData.telefono || "",
      celular: initialData.celular || "",
      email: initialData.email || "",
      firma_digital: initialData.firma_digital || "",
      huella_digital: initialData.huella_digital || "",
      fotografia: initialData.fotografia || "",
    });
  }, [initialData]);
  const [error, setError] = useState("");
  useEffect(() => {
    if (form.edad_unidad === "años" && form.edad && Number(form.edad) > 150) {
      setError("La edad no puede superar los 150 años.");
    } else {
      setError("");
    }
  }, [form.edad, form.edad_unidad]);
  const [loading, setLoading] = useState(false);
  const [departamentos, setDepartamentos] = useState([]);
  const [provincias, setProvincias] = useState([]);
  const [distritos, setDistritos] = useState([]);
  const [departamentoId, setDepartamentoId] = useState("");
  const [provinciaId, setProvinciaId] = useState("");
  const [ubigeoError, setUbigeoError] = useState("");

  useEffect(() => {
    let cancelled = false;
    authFetch("api_ubigeo.php?tipo=departamentos")
      .then((res) => res.json())
      .then((data) => {
        if (cancelled) return;
        if (data?.success) {
          setDepartamentos(Array.isArray(data.data) ? data.data : []);
          setUbigeoError("");
        } else {
          setUbigeoError(data?.error || "No se pudo cargar departamentos");
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUbigeoError("No se pudo cargar catálogo ubigeo");
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const cargarProvincias = async (depId) => {
    if (!depId) {
      setProvincias([]);
      setProvinciaId("");
      setDistritos([]);
      return;
    }
    const res = await authFetch(`api_ubigeo.php?tipo=provincias&departamento_id=${Number(depId)}`);
    const data = await res.json();
    if (data?.success) {
      setProvincias(Array.isArray(data.data) ? data.data : []);
      setUbigeoError("");
      return;
    }
    setUbigeoError(data?.error || "No se pudo cargar provincias");
    setProvincias([]);
  };

  const cargarDistritos = async (provId) => {
    if (!provId) {
      setDistritos([]);
      return;
    }
    const res = await authFetch(`api_ubigeo.php?tipo=distritos&provincia_id=${Number(provId)}`);
    const data = await res.json();
    if (data?.success) {
      setDistritos(Array.isArray(data.data) ? data.data : []);
      setUbigeoError("");
      return;
    }
    setUbigeoError(data?.error || "No se pudo cargar distritos");
    setDistritos([]);
  };

  const onDepartamentoChange = async (event) => {
    const depId = event.target.value;
    setDepartamentoId(depId);
    const dep = departamentos.find((item) => String(item.id) === String(depId));
    setForm((prev) => ({
      ...prev,
      departamento: dep?.nombre || "",
      provincia: "",
      distrito: "",
    }));
    setProvinciaId("");
    setDistritos([]);
    await cargarProvincias(depId);
  };

  const onProvinciaChange = async (event) => {
    const provId = event.target.value;
    setProvinciaId(provId);
    const prov = provincias.find((item) => String(item.id) === String(provId));
    setForm((prev) => ({
      ...prev,
      provincia: prov?.nombre || "",
      distrito: "",
    }));
    await cargarDistritos(provId);
  };

  const onDistritoChange = (event) => {
    const distId = event.target.value;
    const dist = distritos.find((item) => String(item.id) === String(distId));
    setForm((prev) => ({ ...prev, distrito: dist?.nombre || "" }));
  };

  useEffect(() => {
    if (!departamentos.length || departamentoId || !form.departamento) return;
    const dep = departamentos.find(
      (item) => String(item.nombre || "").toUpperCase() === String(form.departamento || "").toUpperCase()
    );
    if (dep) {
      setDepartamentoId(String(dep.id));
      cargarProvincias(dep.id);
    }
  }, [departamentos, departamentoId, form.departamento]);

  useEffect(() => {
    if (!provincias.length || provinciaId || !form.provincia) return;
    const prov = provincias.find(
      (item) => String(item.nombre || "").toUpperCase() === String(form.provincia || "").toUpperCase()
    );
    if (prov) {
      setProvinciaId(String(prov.id));
      cargarDistritos(prov.id);
    }
  }, [provincias, provinciaId, form.provincia]);

  const toDataUrl = (file) =>
    new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result || ""));
      reader.onerror = () => reject(new Error("No se pudo leer el archivo"));
      reader.readAsDataURL(file);
    });

  const handleBioFile = (field) => async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      setForm((prev) => ({ ...prev, [field]: "" }));
      return;
    }
    if (!/^image\/(png|jpeg|jpg)$/i.test(file.type)) {
      setError("Los archivos de biometria deben ser PNG o JPG.");
      event.target.value = "";
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      setError("Cada archivo biometrico debe pesar maximo 2MB.");
      event.target.value = "";
      return;
    }
    try {
      const dataUrl = await toDataUrl(file);
      setForm((prev) => ({ ...prev, [field]: dataUrl }));
      setError("");
    } catch (err) {
      setError(err?.message || "No se pudo cargar el archivo biometrico.");
    }
  };

  const guardarPacienteFallback = async (pacientePayload) => {
    try {
      const res = await authFetch("api_pacientes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(pacientePayload),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success || !data?.paciente) {
        return { success: false, error: data?.error || "Error al guardar paciente" };
      }
      return { success: true, paciente: data.paciente };
    } catch {
      return { success: false, error: "Error de conexión con el servidor" };
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name === "tipo_documento") {
      setForm({
        ...form,
        tipo_documento: value,
        dni: value === "sin_documento" ? "" : form.dni,
      });
      return;
    }
    if (name === "dni" && form.tipo_documento === "sin_documento") {
      return;
    }
    if (name === "fecha_nacimiento" && value) {
      const hoy = new Date();
      const fechaNac = new Date(value);
      let edad = "";
      let unidad = "años";
      const diffMs = hoy - fechaNac;
      const diffDias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
      if (diffDias < 0) {
        edad = "";
      } else if (diffDias < 31) {
        edad = diffDias;
        unidad = "días";
      } else if (diffDias < 365) {
        const diffMeses =
          (hoy.getFullYear() - fechaNac.getFullYear()) * 12 +
          hoy.getMonth() -
          fechaNac.getMonth();
        edad = diffMeses;
        unidad = "meses";
      } else {
        let diffAnios = hoy.getFullYear() - fechaNac.getFullYear();
        const m = hoy.getMonth() - fechaNac.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < fechaNac.getDate())) {
          diffAnios--;
        }
        edad = diffAnios;
        unidad = "años";
      }
      setForm({
        ...form,
        fecha_nacimiento: value,
        edad: edad,
        edad_unidad: unidad,
      });
    } else {
      setForm({ ...form, [name]: value });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError("");
    try {
      let formToSend = { ...form };
      const camposMayuscula = [
        "nombre",
        "apellido",
        "historia_clinica",
        "procedencia",
        "direccion",
        "tipo_seguro",
        "lugarnacimiento",
        "calle",
        "urbanizacion",
        "ocupacion",
        "departamento",
        "provincia",
        "distrito",
        "gradoinstruccion",
        "estadocivil",
        "nombrepadre",
        "nombremadre",
        "acompanante"
      ];
      camposMayuscula.forEach((campo) => {
        if (formToSend[campo]) {
          formToSend[campo] = formToSend[campo].toUpperCase();
        }
      });
      if (formToSend.email) {
        formToSend.email = formToSend.email.trim();
      }
      if (formToSend.tipo_documento === "dni") {
        if (!/^\d{8}$/.test(formToSend.dni)) {
          setError("El DNI debe tener exactamente 8 dígitos.");
          return;
        }
      } else if (formToSend.tipo_documento === "carnet_extranjeria") {
        if (!/^\d{12}$/.test(formToSend.dni)) {
          setError("El Carnet de extranjería debe tener exactamente 12 dígitos.");
          return;
        }
      } else if (formToSend.tipo_documento === "sin_documento") {
        formToSend.dni = (99990000 + Math.floor(Math.random() * 100)).toString();
      }

      if (formToSend.tipo_documento === "dni" && formToSend.dni) {
        try {
          const resDni = await authFetch(`api_pacientes.php?busqueda=${formToSend.dni}&limit=1`);
          const dataDni = await resDni.json();
          if (dataDni.success && Array.isArray(dataDni.pacientes) && dataDni.pacientes.length > 0) {
            const pacienteEncontrado = dataDni.pacientes[0];
            if (!form.id || pacienteEncontrado.id !== form.id) {
              Swal.fire({
                icon: "warning",
                title: "DNI ya registrado",
                html: `<div style='font-size:1.1em'><b>El DNI ingresado ya está registrado en el sistema.</b><br>Verifique los datos o busque el paciente existente.</div>`,
                confirmButtonText: "Aceptar",
                showClass: { popup: 'animate__animated animate__fadeInDown' },
                hideClass: { popup: 'animate__animated animate__fadeOutUp' }
              });
              return;
            }
          }
        } catch {
          // Error al consultar DNI, ignorado intencionalmente
        }
      }

      const guardar = typeof guardarPaciente === "function" ? guardarPaciente : guardarPacienteFallback;
      const result = await guardar(formToSend);

      if (result.success) {
        if (typeof onRegistroExitoso === "function") {
          onRegistroExitoso(result.paciente);
        }
        Swal.fire({
          icon: "success",
          title: form.id ? "Paciente actualizado" : "Paciente registrado",
          html: `<b>Historia Clínica:</b> ${result.paciente?.historia_clinica || '-'}`,
          confirmButtonText: "Aceptar"
        });
      } else {
        setError(result.error || "Error al guardar paciente");
        Swal.fire({
          icon: "error",
          title: form.id ? "Error al actualizar paciente" : "Error al registrar paciente",
          html: `<div style='font-size:1.1em'>${result.error || (form.id ? "Error al actualizar paciente" : "Error al registrar paciente")}</div>`,
          confirmButtonText: "Aceptar",
          showClass: { popup: 'animate__animated animate__fadeInDown' },
          hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });
      }
    } catch (err) {
      const message = err?.message || "Error inesperado al registrar paciente";
      setError(message);
      Swal.fire({
        icon: "error",
        title: form.id ? "Error al actualizar paciente" : "Error al registrar paciente",
        html: `<div style='font-size:1.1em'>${message}</div>`,
        confirmButtonText: "Aceptar",
        showClass: { popup: 'animate__animated animate__fadeInDown' },
        hideClass: { popup: 'animate__animated animate__fadeOutUp' }
      });
    } finally {
      setLoading(false);
    }
  };

  const submitBtnRef = useRef(null);

  const handleKeyDown = (e) => {
    if (e.key === "Enter") {
      if (document.activeElement !== submitBtnRef.current) {
        e.preventDefault();
      }
    }
  };

  return (
    <div className="w-full h-full px-2 sm:px-0">
      <h2
        className="text-2xl font-extrabold mb-6 flex items-center gap-3 justify-center bg-gradient-to-r from-purple-700 via-pink-500 to-blue-500 text-white rounded-xl shadow-lg py-4 px-6 animate__animated animate__fadeInDown"
        style={{ boxShadow: '0 4px 16px rgba(80,0,120,0.12)' }}
      >
        <svg
          className="w-8 h-8 text-white drop-shadow"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 7v4m0 4h.01"
          />
        </svg>
        {form.id ? "Editar Paciente" : "Registrar Nuevo Paciente"}
      </h2>
      {error && (
        <div className="mb-3 p-2 bg-red-100 text-red-700 rounded text-center font-semibold">
          {error}
        </div>
      )}
      <form
        onSubmit={handleSubmit}
        onKeyDown={handleKeyDown}
        className="flex flex-col space-y-4 bg-blue-50 p-4 rounded border border-blue-200 h-full w-full overflow-y-auto"
        style={{ minHeight: '60vh', maxHeight: '70vh' }}
      >
        <DatosBasicos form={form} handleChange={handleChange} />
        <DatosEdad form={form} handleChange={handleChange} />
        <DatosAdicionales
          form={form}
          handleChange={handleChange}
          departamentos={departamentos}
          provincias={provincias}
          distritos={distritos}
          departamentoId={departamentoId}
          provinciaId={provinciaId}
          onDepartamentoChange={onDepartamentoChange}
          onProvinciaChange={onProvinciaChange}
          onDistritoChange={onDistritoChange}
        />
        {ubigeoError ? (
          <div className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {ubigeoError}
          </div>
        ) : null}
        <DatosContacto form={form} handleChange={handleChange} />
        <div className="border border-indigo-200 bg-indigo-50 rounded-lg p-4">
          <h3 className="text-sm font-bold text-indigo-800 mb-3">Biometria del paciente</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-semibold text-indigo-700 mb-1">Firma digital</label>
              <input
                type="file"
                accept="image/png,image/jpeg"
                onChange={handleBioFile("firma_digital")}
                className="w-full text-xs border border-indigo-200 rounded px-2 py-1 bg-white"
              />
              {form.firma_digital ? (
                <img src={form.firma_digital} alt="Firma digital" className="mt-2 h-20 w-full object-contain rounded bg-white border border-indigo-100" />
              ) : null}
            </div>
            <div>
              <label className="block text-xs font-semibold text-indigo-700 mb-1">Huella digital</label>
              <input
                type="file"
                accept="image/png,image/jpeg"
                onChange={handleBioFile("huella_digital")}
                className="w-full text-xs border border-indigo-200 rounded px-2 py-1 bg-white"
              />
              {form.huella_digital ? (
                <img src={form.huella_digital} alt="Huella digital" className="mt-2 h-20 w-full object-contain rounded bg-white border border-indigo-100" />
              ) : null}
            </div>
            <div>
              <label className="block text-xs font-semibold text-indigo-700 mb-1">Fotografia</label>
              <input
                type="file"
                accept="image/png,image/jpeg"
                onChange={handleBioFile("fotografia")}
                className="w-full text-xs border border-indigo-200 rounded px-2 py-1 bg-white"
              />
              {form.fotografia ? (
                <img src={form.fotografia} alt="Fotografia" className="mt-2 h-20 w-full object-cover rounded bg-white border border-indigo-100" />
              ) : null}
            </div>
          </div>
        </div>
        <div className="fixed left-0 right-0 bottom-0 z-10 bg-blue-50 p-4 border-t border-blue-200 w-full sm:static sm:w-auto sm:p-0 sm:border-0">
          <button
            type="submit"
            ref={submitBtnRef}
            className="w-full bg-purple-800 hover:bg-purple-900 text-white rounded-lg px-4 py-3 font-bold text-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
            disabled={loading}
          >
            {loading ? (
              <div className="flex items-center justify-center gap-2">
                <svg
                  className="animate-spin h-5 w-5"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                  ></circle>
                  <path
                    className="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
                {form.id ? "Actualizando..." : "Registrando..."}
              </div>
            ) : form.id ? (
              "Actualizar Paciente"
            ) : (
              "Registrar Paciente"
            )}
          </button>
        </div>
      </form>
    </div>
  );
}

export default PacienteListForm;
