import { useState, useEffect } from 'react';
import axios from 'axios';

export function useDatosUsuario() {
  const [datos, setDatos] = useState({
    metricas: null,
    horasPorDia: [],
    turnos: [],
    cargando: true,
    error: null
  });

  useEffect(() => {
    const obtenerDatos = async () => {
      try {
        // Esta URL la cambiaremos cuando subamos a InfinityFree
        const respuesta = await axios.get('/api_metricas.php');
        
        setDatos({
          metricas: respuesta.data.metricas,
          horasPorDia: respuesta.data.horas_por_dia,
          turnos: respuesta.data.turnos,
          cargando: false,
          error: null
        });
      } catch (error) {
        setDatos({
          ...datos,
          cargando: false,
          error: error.message
        });
      }
    };

    obtenerDatos();
  }, []);

  return datos;
}