import React from "react";

function DatosAdicionales({
  form,
  handleChange,
  departamentos = [],
  provincias = [],
  distritos = [],
  departamentoId = "",
  provinciaId = "",
  onDepartamentoChange,
  onProvinciaChange,
  onDistritoChange,
}) {
  return (
    <div className="bg-white rounded-lg p-4 border border-blue-300">
      <h3 className="text-lg font-semibold text-blue-800 mb-3 flex items-center gap-2">
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        Información Adicional
      </h3>
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Lugar de nacimiento</label>
            <input name="lugarnacimiento" value={form.lugarnacimiento || ""} onChange={handleChange} placeholder="Ciudad de nacimiento" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Ocupación</label>
            <input name="ocupacion" value={form.ocupacion || ""} onChange={handleChange} placeholder="Profesión u ocupación" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Procedencia</label>
          <input name="procedencia" value={form.procedencia} onChange={handleChange} placeholder="Ciudad o lugar de procedencia" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
          <textarea name="direccion" value={form.direccion} onChange={handleChange} placeholder="Dirección completa del paciente" rows="2" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" />
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Num/DPTO/INT</label>
            <input name="calle" value={form.calle || ""} onChange={handleChange} placeholder="Detalle de vivienda" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Urbanización / AA.HH.</label>
            <input name="urbanizacion" value={form.urbanizacion || ""} onChange={handleChange} placeholder="Urbanización o AA.HH." className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
            <select
              name="departamento"
              value={departamentoId}
              onChange={onDepartamentoChange}
              className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Seleccione departamento</option>
              {departamentos.map((item) => (
                <option key={item.id} value={item.id}>{item.nombre}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
            <select
              name="provincia"
              value={provinciaId}
              onChange={onProvinciaChange}
              disabled={!departamentoId}
              className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
            >
              <option value="">Seleccione provincia</option>
              {provincias.map((item) => (
                <option key={item.id} value={item.id}>{item.nombre}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Distrito</label>
            <select
              name="distrito"
              value={
                (distritos.find((item) => String(item.nombre || "").toUpperCase() === String(form.distrito || "").toUpperCase())?.id || "")
              }
              onChange={onDistritoChange}
              disabled={!provinciaId}
              className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
            >
              <option value="">Seleccione distrito</option>
              {distritos.map((item) => (
                <option key={item.id} value={item.id}>{item.nombre}</option>
              ))}
            </select>
          </div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nro Hijos vivos</label>
            <input name="hijos" value={form.hijos ?? ""} onChange={handleChange} type="number" min="0" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nro Hijos dependientes</label>
            <input name="hijosdependientes" value={form.hijosdependientes ?? ""} onChange={handleChange} type="number" min="0" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Reside cerca del trabajo</label>
            <select name="trabajoresidencia" value={String(form.trabajoresidencia ?? "")} onChange={handleChange} className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">Seleccione</option>
              <option value="1">Si</option>
              <option value="0">No</option>
            </select>
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Tiempo de residencia (años)</label>
          <input name="tiemporesidencia" value={form.tiemporesidencia ?? ""} onChange={handleChange} type="number" min="0" disabled={String(form.trabajoresidencia ?? "") !== "1"} className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100" />
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Grado de instrucción</label>
            <input name="gradoinstruccion" value={form.gradoinstruccion || ""} onChange={handleChange} placeholder="Grado de instrucción" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Estado civil</label>
            <input name="estadocivil" value={form.estadocivil || ""} onChange={handleChange} placeholder="Estado civil" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tipo de Seguro</label>
            <input name="tipo_seguro" value={form.tipo_seguro} onChange={handleChange} placeholder="Ej: SIS, EsSalud, Particular" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nombre de papá</label>
            <input name="nombrepadre" value={form.nombrepadre || ""} onChange={handleChange} placeholder="Nombre del padre" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nombre de mamá</label>
            <input name="nombremadre" value={form.nombremadre || ""} onChange={handleChange} placeholder="Nombre de la madre" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Acompañante</label>
            <input name="acompanante" value={form.acompanante || ""} onChange={handleChange} placeholder="Nombre del acompañante" className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>
        </div>
      </div>
    </div>
  );
}

export default DatosAdicionales;
