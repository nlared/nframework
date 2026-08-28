// Consultas base para MongoDB.
// Ajusta el nombre de la base si no usas 'sat'.

const dbName = 'sat';

function vigenciaPorFecha(collectionName, tipo, fecha) {
  return db.getSiblingDB(dbName).getCollection(collectionName).find({
    tipo: tipo,
    vigente_desde: { $lte: ISODate(fecha) }
  }).sort({ vigente_desde: -1 }).limit(1).next();
}

function salarioVigente(fecha) {
  return db.getSiblingDB(dbName).salarios_minimos.find({
    vigente_desde: { $lte: ISODate(fecha) }
  }).sort({ vigente_desde: -1 }).limit(1).next();
}

function umaVigente(fecha) {
  return db.getSiblingDB(dbName).uma.find({
    vigente_desde: { $lte: ISODate(fecha) }
  }).sort({ vigente_desde: -1 }).limit(1).next();
}

function isrPorAnio(anio) {
  return db.getSiblingDB(dbName).tramos_impuesto.findOne({ tipo: 'isr', anio: anio });
}

function subisrPorAnio(anio) {
  return db.getSiblingDB(dbName).tramos_impuesto.findOne({ tipo: 'subisr', anio: anio });
}

function tramoISR(anio, valor) {
  const doc = db.getSiblingDB(dbName).tramos_impuesto.findOne({ tipo: 'isr', anio: anio });
  if (!doc) {
    return null;
  }

  return doc.tramos.find(tramo => {
    const superior = tramo.limite_superior === null ? Infinity : tramo.limite_superior;
    return valor >= tramo.limite_inferior && valor <= superior;
  });
}

function tramoSUBISR(anio, valor) {
  const doc = db.getSiblingDB(dbName).tramos_impuesto.findOne({ tipo: 'subisr', anio: anio });
  if (!doc) {
    return null;
  }

  return doc.tramos.find(tramo => {
    const superior = tramo.limite_superior === null ? Infinity : tramo.limite_superior;
    return valor >= tramo.limite_inferior && valor <= superior;
  });
}

function calcularISR(anio, valor, mes = 1, factor = 1) {
  const ajuste = valor * factor;
  const tramo = tramoISR(anio, ajuste * mes);
  if (!tramo) {
    return null;
  }

  const limite = tramo.limite_inferior * mes;
  const superior = tramo.limite_superior === null ? Infinity : tramo.limite_superior * mes;
  const excedente = Math.max(0, ajuste - limite);
  const isr = (excedente * tramo.tasa / 100) + (tramo.cuota_fija * mes);

  return {
    limite: limite,
    limitesuperior: superior,
    fija: tramo.cuota_fija * mes,
    tasa: tramo.tasa,
    excedente: excedente,
    isr: isr,
    tasaefectiva: ajuste > 0 ? (isr * 100 / ajuste) : 0
  };
}

function calcularSubsidio(anio, valor) {
  const tramo = tramoSUBISR(anio, valor);
  if (!tramo) {
    return null;
  }

  return {
    inferior: tramo.limite_inferior,
    superior: tramo.limite_superior,
    subsidio: tramo.subsidio
  };
}

function calcularIMSS(anio, sueldo, claseRiesgo = 1) {
  const dbSat = db.getSiblingDB(dbName);
  const riesgoDoc = dbSat.riesgo_trabajo.findOne({ anio: anio });
  const cuotas = dbSat.cuotas_base.find().toArray();

  if (!riesgoDoc) {
    return null;
  }

  const primaRiesgo = (riesgoDoc.clases.find(item => item.clase === claseRiesgo) || riesgoDoc.clases.find(item => item.clase === 1));
  const resultado = {};

  cuotas.forEach(cuota => {
    const patron = cuota.concepto === 'Riesgos de Trabajo' ? primaRiesgo.prima : cuota.patron;
    const trabajador = cuota.concepto === 'Riesgos de Trabajo' ? 0 : cuota.trabajador;

    resultado[cuota.concepto] = {
      patron: sueldo * patron,
      trabajador: sueldo * trabajador,
      patronp: patron,
      trabajadorp: trabajador
    };
  });

  resultado['Riesgos de Trabajo'] = {
    patron: sueldo * primaRiesgo.prima,
    trabajador: 0,
    patronp: primaRiesgo.prima,
    trabajadorp: 0
  };

  return resultado;
}

function factorIntegracion(anio) {
  const salario = db.getSiblingDB(dbName).salarios_minimos.findOne({ anio: anio });
  const uma = db.getSiblingDB(dbName).uma.findOne({ anio: anio });

  if (!salario || !uma) {
    return null;
  }

  const sueldo = salario.zonas.A;
  return (sueldo + uma.valor) / sueldo;
}

// Ejemplos:
// printjson(isrPorAnio(2024));
// printjson(tramoISR(2024, 10000));
// printjson(calcularISR(2024, 10000));
// printjson(calcularSubsidio(2018, 5000));
// printjson(calcularIMSS(2026, 15000, 2));
// printjson(factorIntegracion(2026));
