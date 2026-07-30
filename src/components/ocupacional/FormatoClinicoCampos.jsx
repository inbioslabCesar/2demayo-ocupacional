function CampoClinico({ label, value, onChange, type = "text", unit = "", multiline = false, disabled = false }) {
  return (
    <label className="block min-w-0 text-xs font-medium text-slate-700">
      <span className="mb-1 block">{label}</span>
      <span className="flex items-center rounded border border-slate-300 bg-white focus-within:border-cyan-500 focus-within:ring-1 focus-within:ring-cyan-500">
        {multiline ? (
          <textarea className="min-h-20 w-full resize-y rounded border-0 px-2 py-1.5 text-xs outline-none" value={value ?? ""} onChange={(event) => onChange(event.target.value)} disabled={disabled} />
        ) : (
          <input type={type} className="min-w-0 flex-1 rounded border-0 px-2 py-1.5 text-xs outline-none" value={value ?? ""} onChange={(event) => onChange(event.target.value)} disabled={disabled} />
        )}
        {!multiline && unit ? <span className="shrink-0 pr-2 text-[11px] text-slate-500">{unit}</span> : null}
      </span>
    </label>
  );
}

function SeleccionClinica({ label, value, options, onChange, disabled }) {
  return (
    <label className="block min-w-0 text-xs font-medium text-slate-700">
      <span className="mb-1 block">{label}</span>
      <select className="h-9 w-full rounded border border-slate-300 bg-white px-2 text-xs outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500" value={value ?? ""} onChange={(event) => onChange(event.target.value)} disabled={disabled}>
        {options.map(([optionValue, optionLabel]) => <option key={optionValue} value={optionValue}>{optionLabel}</option>)}
      </select>
    </label>
  );
}

function SeccionClinica({ title, children }) {
  return (
    <section className="overflow-hidden rounded border border-slate-200 bg-white">
      <h4 className="border-b border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-bold uppercase text-cyan-900">{title}</h4>
      <div className="p-3">{children}</div>
    </section>
  );
}

export default function FormatoClinicoCampos({ templateCode, datos, onChange, onAudiometriaChange, onParametroChange, disabled }) {
  const safeDatos = datos && typeof datos === "object" ? datos : {};
  const field = (key, label, options = {}) => <CampoClinico key={key} label={label} value={safeDatos[key]} onChange={(value) => onChange(key, value)} disabled={disabled} {...options} />;

  if (templateCode === "triaje_clinico") {
    return (
      <div className="space-y-3 rounded border border-slate-200 bg-slate-50 p-3">
        <h4 className="text-sm font-semibold text-slate-800">Signos vitales y antropometria</h4>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {field("presion_sistolica", "Presion sistolica", { type: "number", unit: "mmHg" })}
          {field("presion_diastolica", "Presion diastolica", { type: "number", unit: "mmHg" })}
          {field("frecuencia_cardiaca", "Frecuencia cardiaca", { type: "number", unit: "lpm" })}
          {field("frecuencia_respiratoria", "Frecuencia respiratoria", { type: "number", unit: "rpm" })}
          {field("temperatura", "Temperatura", { type: "number", unit: "°C" })}
          {field("saturacion_oxigeno", "Saturacion de oxigeno", { type: "number", unit: "%" })}
          {field("peso_kg", "Peso", { type: "number", unit: "kg" })}
          {field("talla_cm", "Talla", { type: "number", unit: "cm" })}
          {field("perimetro_abdominal_cm", "Perimetro abdominal", { type: "number", unit: "cm" })}
          <CampoClinico label="IMC" value={safeDatos.imc} onChange={() => {}} unit="kg/m2" disabled />
        </div>
        {field("observaciones", "Observaciones", { multiline: true })}
      </div>
    );
  }

  if (templateCode === "audiometria_basica") {
    const frequencies = ["500", "1000", "2000", "3000", "4000", "6000", "8000"];
    return (
      <div className="space-y-3 rounded border border-slate-200 bg-slate-50 p-3">
        <h4 className="text-sm font-semibold text-slate-800">Umbrales audiometricos</h4>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[680px] border-collapse text-xs">
            <thead><tr><th className="border border-slate-300 bg-slate-100 p-2 text-left">Oido</th>{frequencies.map((frequency) => <th key={frequency} className="border border-slate-300 bg-slate-100 p-2 text-center">{frequency} Hz</th>)}</tr></thead>
            <tbody>{[["od", "Derecho"], ["oi", "Izquierdo"]].map(([ear, label]) => <tr key={ear}><th className="border border-slate-300 p-2 text-left">{label}</th>{frequencies.map((frequency) => <td key={frequency} className="border border-slate-300 p-1"><input type="number" className="w-full min-w-16 rounded border border-slate-200 px-1 py-1 text-center" value={safeDatos[ear]?.[frequency] ?? ""} onChange={(event) => onAudiometriaChange(ear, frequency, event.target.value)} disabled={disabled} /></td>)}</tr>)}</tbody>
          </table>
        </div>
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">{field("otoscopia_od", "Otoscopia OD")}{field("otoscopia_oi", "Otoscopia OI")}</div>
        {field("impresion", "Impresion audiometrica", { multiline: true })}
        {field("recomendaciones", "Recomendaciones", { multiline: true })}
      </div>
    );
  }

  if (templateCode === "lab_basico") {
    const parametros = Array.isArray(safeDatos.parametros) ? safeDatos.parametros : [];
    const updateParametro = (index, key, value) => {
      if (onParametroChange) {
        onParametroChange(index, key, value);
        return;
      }
      onChange("parametros", parametros.map((parametro, currentIndex) => currentIndex === index ? { ...parametro, [key]: value } : parametro));
    };
    const addParametro = () => onChange("parametros", [...parametros, { grupo: "", nombre: "", valor: "", unidad: "", referencia: "" }]);
    const removeParametro = (index) => onChange("parametros", parametros.filter((_, currentIndex) => currentIndex !== index));
    return (
      <div className="space-y-3 rounded border border-slate-200 bg-slate-50 p-3">
        <SeccionClinica title="Datos de la evaluación">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
            {field("responsable_evaluacion", "Responsable de la evaluación")}
            {field("tipo_muestra", "Tipo de muestra")}
            {field("condiciones_muestra", "Condiciones de la muestra")}
          </div>
        </SeccionClinica>
        <SeccionClinica title="Resultados de laboratorio">
          <div className="space-y-2">
            {parametros.length === 0 ? <p className="text-xs text-slate-500">No hay parámetros registrados.</p> : null}
            {parametros.map((parametro, index) => (
              <div key={`${parametro.nombre || "parametro"}-${index}`} className="grid grid-cols-1 gap-2 rounded border border-slate-200 bg-slate-50 p-2 sm:grid-cols-2 xl:grid-cols-[1fr_1.4fr_1fr_0.7fr_1fr_auto]">
                <CampoClinico label="Grupo" value={parametro.grupo} onChange={(value) => updateParametro(index, "grupo", value)} disabled={disabled} />
                <CampoClinico label="Parámetro" value={parametro.nombre} onChange={(value) => updateParametro(index, "nombre", value)} disabled={disabled} />
                <CampoClinico label="Resultado" value={parametro.valor} onChange={(value) => updateParametro(index, "valor", value)} disabled={disabled} />
                <CampoClinico label="Unidad" value={parametro.unidad} onChange={(value) => updateParametro(index, "unidad", value)} disabled={disabled} />
                <CampoClinico label="Referencia" value={parametro.referencia} onChange={(value) => updateParametro(index, "referencia", value)} disabled={disabled} />
                <button type="button" className="h-9 self-end rounded border border-red-200 px-3 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-40" onClick={() => removeParametro(index)} disabled={disabled} aria-label={`Eliminar parámetro ${index + 1}`}>Eliminar</button>
              </div>
            ))}
          </div>
          <button type="button" className="mt-3 rounded border border-cyan-300 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-50 disabled:opacity-40" onClick={addParametro} disabled={disabled}>Agregar parámetro</button>
        </SeccionClinica>
        <SeccionClinica title="Interpretación">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            {field("hallazgos", "Hallazgos", { multiline: true })}
            {field("conclusion", "Conclusión", { multiline: true })}
            <div className="md:col-span-2">{field("recomendaciones", "Recomendaciones", { multiline: true })}</div>
          </div>
        </SeccionClinica>
      </div>
    );
  }

  if (templateCode === "psicologia_basica") {
    const legacyData = [safeDatos.hallazgos, safeDatos.diagnostico, safeDatos.conclusion].some((value) => String(value || "").trim() !== "");
    const select = (key, label, options) => <SeleccionClinica key={key} label={label} value={safeDatos[key]} options={options} onChange={(value) => onChange(key, value)} disabled={disabled} />;
    return (
      <div className="space-y-3 rounded border border-slate-200 bg-slate-50 p-3">
        <SeccionClinica title="Responsable y motivo de evaluación">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            {field("responsable_evaluacion", "Responsable de la evaluación")}
            {field("motivo_evaluacion", "Motivo de evaluación")}
          </div>
        </SeccionClinica>
        <SeccionClinica title="Observación de conductas">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {select("presentacion", "Presentación", [["adecuada", "Adecuada"], ["inadecuada", "Inadecuada"]])}
            {select("postura", "Postura", [["erguida", "Erguida"], ["encorvada", "Encorvada"]])}
            {select("discurso_ritmo", "Ritmo del discurso", [["lento", "Lento"], ["rapido", "Rápido"], ["fluido", "Fluido"]])}
            {select("discurso_tono", "Tono del discurso", [["bajo", "Bajo"], ["moderado", "Moderado"], ["alto", "Alto"]])}
            {select("discurso_articulacion", "Articulación", [["con_dificultad", "Con dificultad"], ["sin_dificultad", "Sin dificultad"]])}
            {select("orientacion_tiempo", "Orientación en tiempo", [["orientado", "Orientado"], ["desorientado", "Desorientado"]])}
            {select("orientacion_espacio", "Orientación en espacio", [["orientado", "Orientado"], ["desorientado", "Desorientado"]])}
            {select("orientacion_persona", "Orientación en persona", [["orientado", "Orientado"], ["desorientado", "Desorientado"]])}
          </div>
        </SeccionClinica>
        <SeccionClinica title="Resultados de la evaluación">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            {field("nivel_intelectual", "Nivel intelectual")}
            {field("coordinacion_visomotriz", "Coordinación visomotriz")}
            {field("nivel_memoria", "Nivel de memoria")}
            {field("personalidad", "Personalidad")}
            {field("afectividad", "Afectividad")}
          </div>
        </SeccionClinica>
        <SeccionClinica title="Conclusiones y recomendaciones">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            {field("conclusion_cognitiva", "Área cognitiva", { multiline: true })}
            {field("conclusion_emocional", "Área emocional", { multiline: true })}
            {field("recomendaciones", "Recomendaciones", { multiline: true })}
            {field("observaciones", "Observaciones", { multiline: true })}
          </div>
        </SeccionClinica>
        {legacyData ? (
          <SeccionClinica title="Información del formato anterior">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              {field("hallazgos", "Hallazgos anteriores", { multiline: true })}
              {field("diagnostico", "Diagnóstico anterior", { multiline: true })}
              <div className="md:col-span-2">{field("conclusion", "Conclusión anterior", { multiline: true })}</div>
            </div>
          </SeccionClinica>
        ) : null}
      </div>
    );
  }

  const camposPorTemplate = templateCode === "evaluacion_medica_ocupacional"
    ? [["motivo_evaluacion", "Motivo de evaluacion"], ["antecedentes_ocupacionales", "Antecedentes ocupacionales"], ["antecedentes_personales", "Antecedentes personales"], ["anamnesis", "Anamnesis"], ["examen_fisico", "Examen fisico"], ["diagnostico", "Diagnostico"], ["conclusion", "Conclusion"], ["recomendaciones", "Recomendaciones"]]
    : templateCode === "oftalmologia_basica"
      ? [["agudeza_visual_od", "Agudeza visual OD"], ["agudeza_visual_oi", "Agudeza visual OI"], ["vision_colores", "Vision de colores"], ["impresion", "Impresion oftalmologica"], ["recomendaciones", "Recomendaciones"]]
      : templateCode === "ekg_basico"
        ? [["ritmo", "Ritmo"], ["frecuencia", "Frecuencia"], ["eje", "Eje"], ["hallazgos", "Hallazgos"], ["conclusion", "Conclusion"]]
        : [["motivo", "Motivo"], ["hallazgos", "Hallazgos"], ["conclusion", "Conclusion"], ["recomendaciones", "Recomendaciones"]];

  return <div className="grid grid-cols-1 gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-2">{camposPorTemplate.map(([key, label]) => field(key, label, { multiline: true }))}</div>;
}