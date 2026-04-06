import { useEffect, useRef } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Line,
  PieChart, Pie, Cell, ResponsiveContainer
} from 'recharts';

// Colores exactos de FIGMA
const COLORES = {
  puntualidad: '#10b981',
  colaboracion: '#f59e0b',
  asistencia: '#3b82f6',
  productividad: '#8b5cf6',
  horasTrabajadas: '#3b82f6',
  objetivo: '#f59e0b'
};

export default function GraficosFigma({ metricas, horasPorDia, cargando, error }) {
  // Preparar datos para el gráfico de barras
  const diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  
  // Convertir horasPorDia (array de objetos con fecha y horas_trabajadas) a formato para Recharts
  const datosBarra = diasSemana.map((dia, index) => {
    const registro = horasPorDia?.find(r => {
      if (!r?.fecha) return false;
      const fechaObj = new Date(r.fecha);
      const diaNum = fechaObj.getDay();
      // Ajustar para que Lunes sea 1, Domingo 7
      const diaAjustado = diaNum === 0 ? 7 : diaNum;
      return diaAjustado === index + 1;
    });
    
    return {
      dia: dia,
      horas: registro?.horas_trabajadas || 0,
      objetivo: 8
    };
  });

  // Datos para el pie chart
  const datosPie = [
    { name: 'Puntualidad', value: metricas?.puntualidad || 0, color: COLORES.puntualidad },
    { name: 'Colaboración', value: metricas?.colaboracion || 0, color: COLORES.colaboracion },
    { name: 'Asistencia', value: metricas?.asistencia || 0, color: COLORES.asistencia },
    { name: 'Productividad', value: metricas?.productividad || 0, color: COLORES.productividad }
  ];

  if (cargando) {
    return (
      <div className="flex items-center justify-center h-64 bg-white rounded-2xl shadow-figma">
        <div className="text-center">
          <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500 mx-auto"></div>
          <p className="mt-4 text-gray-500">Cargando métricas...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-2xl p-6 text-center">
        <p className="text-red-600">Error al cargar datos: {error}</p>
        <button 
          onClick={() => window.location.reload()} 
          className="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg"
        >
          Reintentar
        </button>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      
      {/* GRÁFICO 1: PIE CHART - Métricas de Rendimiento */}
      <div className="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
        <div className="flex justify-between items-center mb-6">
          <h3 className="text-lg font-semibold flex items-center gap-2 text-gray-800">
            <span className="w-2 h-2 bg-blue-500 rounded-full"></span>
            Métricas de Rendimiento
          </h3>
          <span className="text-xs px-3 py-1 bg-gray-100 text-gray-500 rounded-full">
            Objetivo: 40h
          </span>
        </div>
        
        <ResponsiveContainer width="100%" height={260}>
          <PieChart>
            <Pie
              data={datosPie}
              cx="50%"
              cy="50%"
              innerRadius={60}
              outerRadius={90}
              paddingAngle={3}
              dataKey="value"
              labelLine={false}
              animationDuration={1000}
            >
              {datosPie.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.color} stroke="none" />
              ))}
            </Pie>
            <Tooltip 
              formatter={(value) => `${value}%`}
              contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
            />
          </PieChart>
        </ResponsiveContainer>
        
        <div className="flex justify-center gap-4 mt-4 flex-wrap">
          {datosPie.map((item) => (
            <span key={item.name} className="flex items-center gap-2 text-sm text-gray-600">
              <span className="w-3 h-3 rounded-full" style={{ background: item.color }}></span>
              {item.name} ({item.value}%)
            </span>
          ))}
        </div>
      </div>

      {/* GRÁFICO 2: BARRAS - Horas por Día (escala 0-12) */}
      <div className="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
        <div className="flex justify-between items-center mb-6">
          <h3 className="text-lg font-semibold flex items-center gap-2 text-gray-800">
            <span className="w-2 h-2 bg-orange-500 rounded-full"></span>
            Horas por Día
          </h3>
        </div>
        
        <ResponsiveContainer width="100%" height={260}>
          <BarChart data={datosBarra} margin={{ top: 10, right: 30, left: 0, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="dia" tick={{ fill: '#475569' }} />
            <YAxis 
              domain={[0, 12]} 
              ticks={[0, 3, 6, 9, 12]} 
              tick={{ fill: '#475569' }} 
              tickFormatter={(value) => `${value}h`}
            />
            <Tooltip 
              formatter={(value, name) => [`${value} horas`, name === 'horas' ? 'Horas Trabajadas' : 'Objetivo']}
              contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
            />
            <Bar dataKey="horas" name="Horas Trabajadas" fill={COLORES.horasTrabajadas} radius={[8, 8, 0, 0]} barSize={40} />
            <Line type="monotone" dataKey="objetivo" name="Objetivo" stroke={COLORES.objetivo} strokeWidth={2} strokeDasharray="5 5" dot={false} />
          </BarChart>
        </ResponsiveContainer>
        
        <div className="flex justify-center gap-6 mt-4">
          <span className="flex items-center gap-2 text-sm text-gray-600">
            <span className="w-4 h-4 bg-blue-500 rounded"></span>
            🟢 Horas Trabajadas
          </span>
          <span className="flex items-center gap-2 text-sm text-gray-600">
            <span className="w-6 h-0.5 bg-orange-500"></span>
            ⚪ Objetivo (8h)
          </span>
        </div>
      </div>
    </div>
  );
}