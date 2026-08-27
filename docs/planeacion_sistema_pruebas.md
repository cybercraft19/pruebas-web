
# Arquitectura y Estrategia: Sistema de Digitalización de Pruebas

## 1. Visión General del Proyecto

Desarrollo de un sistema web para la digitalización de pruebas psicotécnicas y académicas. El sistema permitirá el acceso de estudiantes (individual y grupal), ejecución de pruebas, calificación automática y generación de informes de resultados con recomendaciones.

## 2. Stack Tecnológico

| Componente               | Tecnología                               | Justificación                                                                                       |
| :----------------------- | :---------------------------------------- | :--------------------------------------------------------------------------------------------------- |
| **Backend**        | Java + Spring Boot                        | Robusto, excelente manejo de concurrencia y seguridad (Spring Security) para el manejo de sesiones.  |
| **Base de Datos**  | MySQL                                     | Ideal para diseñar esquemas relacionales complejos (evaluadores, estudiantes, preguntas, puntajes). |
| **Frontend**       | HTML, CSS, JS (Vanilla)                   | Interfaces modernas (ej. estilos*glassmorphism*) y consumo del backend vía Fetch API.             |
| **Visualización** | jQuery DataTables                         | Renderizado rápido, búsqueda y filtrado para el informe general de resultados.                     |
| **Despliegue**     | Vercel (Front) + Railway/Render (Back/BD) | Despliegue continuo desde repositorios Git/GitHub.                                                   |

## 3. Modelo de Datos Dinámico (El núcleo del sistema)

Para evitar "quemar" (hardcodear) las pruebas en el código y permitir que los administradores creen nuevos tests desde un panel, la base de datos debe almacenar la *estructura* de las pruebas:

* **`pruebas`**: Datos generales (id, titulo, instrucciones, tiempo_max).
* **`categorias_evaluacion`**: Dimensiones que mide la prueba (ej. "Aptitud Verbal", "Visual", "Lógico-Matemática").
* **`preguntas`**: El texto del enunciado y su tipo (escala, múltiple, dicotómica).
* **`opciones_respuesta`**: Cada opción tiene un "peso" numérico asociado a una `categoria_evaluacion`.

### Flujo de Datos

1. **Administrador:** Usa el frontend para crear una prueba. El frontend envía un JSON complejo al backend (Spring Boot), que guarda la estructura relacional en MySQL.
2. **Estudiante:** Hace login. El frontend solicita el JSON de la prueba al backend y renderiza las preguntas dinámicamente. Al terminar, envía un arreglo de IDs de las opciones seleccionadas.
3. **Backend (Evaluador):** Suma los pesos de las opciones seleccionadas, los agrupa por categoría y genera el informe de resultados.

### Excepción: Pruebas de Ejecución Motora

Pruebas como el *Trail Making Test (TMT)* no encajan en el modelo relacional estándar.

* Requieren una vista personalizada en el frontend (ej. usando `<canvas>` HTML5).
* El backend solo recibe y almacena los resultados finales (tiempo de ejecución en segundos y número de errores).

## 4. Estrategia de Ejecución (MVP y MVP+1)

Para entregar un producto excelente en poco tiempo, se debe gestionar el alcance y la expectativa del cliente.

1. **Acotar el MVP:** Enfocarse exclusivamente en el motor dinámico para pruebas de selección múltiple, escala y verdadero/falso (ej. Test de Inteligencias Múltiples, Cuestionario de Ansiedad). Postergar las pruebas interactivas/motoras (como el TMT) para una Fase 2.
2. **Herramientas IA:** Utilizar editores de código con IA (Cursor, Gemini CLI) para generar el *boilerplate* de Spring Boot (controladores, entidades JPA) y los scripts DDL de MySQL. Concentrar el esfuerzo humano en el diseño del modelo relacional.
3. **Despliegue Inmediato:** Conectar Git con Vercel para evitar crisis de configuración a última hora y testear la API en producción.
4. **Impacto Visual Frontend:** Usar CSS moderno (glassmorphism) y jQuery DataTables para dar un aspecto premium, limpio y altamente interactivo a los reportes sin requerir diseño complejo.

## 5. Instrucción (Prompt) Inicial para Desarrollo

*Nota para el desarrollador/IA: Utilizar este prompt para iniciar la arquitectura de la base de datos.*

> "Actúa como un Arquitecto de Software y Desarrollador Full-Stack Senior. Vamos a construir un sistema web para digitalizar pruebas psicotécnicas/académicas.
>
> **El Stack Técnico a utilizar es:**
>
> * Backend: Java 17+ con Spring Boot (Maven, Spring Web, Spring Data JPA).
> * Base de Datos: MySQL.
> * Frontend: JavaScript Vanilla (Fetch API), HTML5, CSS3 puro y jQuery DataTables para los reportes.
>
> **Requerimientos iniciales (Motor Dinámico):**
>
> 1. Login seguro para administradores y estudiantes.
> 2. Las pruebas NO están hardcodeadas. Los administradores deben poder crear pruebas, categorías de evaluación, preguntas y opciones de respuesta (con pesos asignados).
> 3. Almacenamiento de respuestas de estudiantes y generación de puntajes por categoría.
>
> **Tu primera tarea:**
> Diseña el modelo relacional altamente dinámico de la base de datos. Entrégame el script SQL (DDL) completo para crear las tablas en MySQL, asegurándote de incluir tablas para parametrizar las pruebas (ej. `pruebas`, `categorias_evaluacion`, `preguntas`, `opciones_respuesta_pesos`) y tablas transaccionales (ej. `respuestas_estudiante`, `resultados_informe`). Asegúrate de aplicar buenas prácticas de normalización."
