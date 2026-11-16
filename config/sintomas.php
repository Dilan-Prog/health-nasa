<?php

/*
|--------------------------------------------------------------------------
| Léxico y parámetros del analizador de síntomas (App\Services\Ia\AnalizadorSintomas)
|--------------------------------------------------------------------------
| SIMULADO y DETERMINISTA: nada de esto es un modelo de lenguaje. Es una tabla de palabras en español.
| Las reglas del razonador NO van aquí (están en config/razonador.php).
|
| Convenciones:
|  - Las frases se escriben con o sin tildes: el analizador las normaliza (minúsculas, sin tildes, ñ = n).
|  - Un '*' al final de una palabra significa "empieza por" (maread* = mareado, mareada, mareados...).
|  - Una frase de varias palabras gana a una de una palabra ("no puedo respirar" gana a la negación "no").
|  - Un elemento de 'frases' puede ser un array: [frase, 'region' => ..., 'calidad' => ..., 'importante' => true...].
|
| TODOS LOS NÚMEROS de este archivo (ventanas, confianzas, valores de intensidad por palabra, minutos de "ayer"...)
| son SUPUESTOS DEL PROYECTO, NO DE LA NORMA. Las banderas rojas son orientación general conservadora (ante la duda,
| escalar), no un protocolo validado: "Pendiente de fuente verificable" / revisión médica pendiente.
*/

return [

    // ---------------------------------------------------------------- parámetros
    'analizador' => [
        // Límite de caracteres que se analizan (el resto se descarta y baja la confianza). Supuesto.
        'max_caracteres' => 500,
        // Una negación ("no", "sin", "ya no", "ni"...) afecta a un síntoma que empiece hasta N palabras después. Supuesto.
        'ventana_negacion' => 4,
        // Si tras una negación va uno de estos ("no me duele MUCHO"), es una negación parcial: el síntoma cuenta como leve.
        'atenuadores_negacion' => ['mucho', 'mucha', 'tanto', 'tanta', 'demasiado', 'demasiada'],
        // Tras "y" / "o", estas palabras abren una oración nueva y cortan la negación ("no tengo mareo y SIENTO náuseas").
        'inicio_clausula' => ['me', 'siento', 'sentia', 'tengo', 'tuve', 'estoy', 'noto', 'hay', 'ademas', 'tambien', 'he', 'ha', 'ahora', 'hoy', 'se', 'le', 'esta', 'sufro', 'padezco'],
        // Palabras que no cuentan como "no reconocidas" al calcular la confianza.
        'palabras_vacias' => [
            'a', 'al', 'algo', 'ahora', 'ante', 'aqui', 'asi', 'bien', 'como', 'con', 'cuando', 'de', 'del', 'desde', 'donde', 'el', 'ella', 'en',
            'es', 'esa', 'ese', 'eso', 'esta', 'estaba', 'estan', 'estar', 'este', 'esto', 'estoy', 'ha', 'hace', 'han', 'hasta', 'hay', 'he', 'hoy',
            'la', 'las', 'le', 'les', 'lo', 'los', 'mas', 'mal', 'me', 'menos', 'mi', 'mis', 'muy', 'noto', 'o', 'para', 'pero', 'por', 'porque', 'que', 'se',
            'sentia', 'siento', 'sentir', 'ser', 'si', 'sobre', 'su', 'sus', 'tambien', 'tengo', 'tenia', 'tiene', 'tuve', 'tipo', 'te', 'un', 'una', 'uno',
            'unos', 'unas', 'y', 'ya', 'yo', 'hola', 'buenas', 'buenos', 'dias', 'tardes', 'noches', 'gracias', 'favor', 'por', 'creo', 'parece', 'veces', 'zona',
            'parte', 'lado', 'region', 'area', 'nivel', 'unos', 'otra', 'otro', 'otras', 'otros', 'todo', 'toda', 'todos', 'todas',
        ],
        // Confianza de interpretación (0-100) de cada entidad. Supuesto del proyecto.
        'confianza' => [
            'base_con_region' => 60,       // síntomas que admiten región (dolor...): base
            'base_sin_region' => 80,       // el resto
            'suma_region' => 20,           // + si se reconoció la región (solo claves con región)
            'suma_lado' => 5,
            'suma_calidad' => 5,
            'suma_intensidad' => 5,
            'suma_duracion' => 5,
            'maximo' => 95,
            'solo_negaciones' => 60,       // confianza global cuando solo se entendieron negaciones
            'penalizacion_max_no_reconocido' => 25,  // se resta hasta este máximo según la proporción de palabras no reconocidas
            'penalizacion_texto_recortado' => 10,
            'chip_usuario' => 100,         // una entidad confirmada o añadida por el usuario con los chips
        ],
        // Una intensidad >= este valor cuenta como "intensa" para la bandera de dolor de cabeza súbito e intenso. Supuesto.
        'intensidad_intensa' => 7,
        // Una hemorragia con intensidad <= este valor (y sin palabras de gravedad) no se marca como importante. Supuesto.
        'intensidad_hemorragia_leve' => 3,
        // Intensidad que se asigna a un síntoma con negación parcial ("no me duele mucho"). Supuesto.
        'intensidad_negacion_parcial' => 2,
        // "N veces" desde este N cuenta como vómito repetido/persistente. Supuesto.
        'vomito_veces_min' => 3,
    ],

    // ---------------------------------------------------------------- duración
    'duracion' => [
        'numeros' => [
            'un' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9,
            'diez' => 10, 'once' => 11, 'doce' => 12, 'quince' => 15, 'veinte' => 20, 'treinta' => 30, 'cuarenta' => 40, 'cincuenta' => 50, 'sesenta' => 60,
            'un par de' => 2,
        ],
        // Minutos de cada unidad.
        'unidades' => [
            'minuto' => 1, 'minutos' => 1, 'min' => 1, 'mins' => 1,
            'hora' => 60, 'horas' => 60, 'h' => 60, 'hr' => 60, 'hrs' => 60,
            'dia' => 1440, 'dias' => 1440,
            'semana' => 10080, 'semanas' => 10080,
        ],
        // "desde ayer"... en minutos. Supuesto: ayer = 24 h, anoche = 12 h, anteayer = 48 h.
        'relativos' => ['ayer' => 1440, 'anoche' => 720, 'anteayer' => 2880, 'antier' => 2880, 'antes de ayer' => 2880],
        'media_hora' => 30,
    ],

    // ---------------------------------------------------------------- síntomas (clave => léxico)
    // 'region' => admite región y lado; 'calidad' => admite calidad (punzante...); 'etiqueta' => nombre del chip.
    'claves' => [
        'dolor' => ['etiqueta' => 'Dolor', 'region' => true, 'calidad' => true, 'frases' => [
            'dolor*', 'dolores', 'duele', 'duelen', 'duele*', 'dolia', 'dolian', 'dolio', 'doliendo', 'adolorid*', 'dolorid*',
            'molestia*', 'molesta', 'molestan', 'molestias',
            ['punzada*', 'calidad' => 'punzante'], ['punzadas', 'calidad' => 'punzante'], ['pinchazo*', 'calidad' => 'punzante'], ['pinchazos', 'calidad' => 'punzante'],
            ['ardor', 'calidad' => 'quemante'], ['quemazon', 'calidad' => 'quemante'], ['me arde', 'calidad' => 'quemante'], ['me quema', 'calidad' => 'quemante'], ['arde', 'calidad' => 'quemante'],
            ['retortijon*', 'calidad' => 'colico', 'region' => 'abdomen'], ['colico*', 'calidad' => 'colico', 'region' => 'abdomen'],
            ['calambre*', 'calidad' => 'calambre'],
            ['cefalea*', 'region' => 'cabeza'], ['jaqueca*', 'region' => 'cabeza'], ['migrana*', 'region' => 'cabeza'],
            // "El peor dolor de mi vida": intensidad 10 y aparición súbita (descriptor clásico de alarma; supuesto conservador).
            ['el peor dolor de cabeza de mi vida', 'region' => 'cabeza', 'subito' => true, 'intensidad' => 10],
            ['peor dolor de cabeza de mi vida', 'region' => 'cabeza', 'subito' => true, 'intensidad' => 10],
            ['el peor dolor de mi vida', 'subito' => true, 'intensidad' => 10], ['peor dolor de mi vida', 'subito' => true, 'intensidad' => 10],
            ['el peor dolor de cabeza', 'region' => 'cabeza', 'subito' => true, 'intensidad' => 10], ['peor dolor de cabeza', 'region' => 'cabeza', 'subito' => true, 'intensidad' => 10],
            ['el peor dolor', 'intensidad' => 10], ['peor dolor', 'intensidad' => 10],
            ['dolor de muelas', 'region' => 'boca'], ['dolor de muela', 'region' => 'boca'],
            ['tortic*', 'region' => 'cuello'], ['lumbago', 'region' => 'espalda baja'], ['ciatica', 'region' => 'espalda baja'],
            ['agujetas', 'region' => 'musculo'], ['contractura*', 'region' => 'musculo', 'calidad' => 'rigidez'],
        ]],
        // "presión/opresión/peso en el pecho": con región pecho es 'presion_pecho' (bandera de dolor torácico).
        'presion' => ['etiqueta' => 'Presión', 'region' => true, 'calidad' => false, 'frases' => [
            'presion', ['opresion', 'region_defecto' => 'pecho'], 'oprime*', 'aprieta*', 'apretad*', 'apreton', 'apretones', 'peso en', 'como un peso', 'como una losa', 'una losa',
        ]],
        // Clave derivada (no tiene frases): dolor + región cabeza.
        'dolor_cabeza' => ['etiqueta' => 'Dolor de cabeza', 'region' => false, 'calidad' => true, 'derivada' => true, 'frases' => []],
        // Clave derivada: 'presion' + región pecho.
        'presion_pecho' => ['etiqueta' => 'Presión en el pecho', 'region' => false, 'calidad' => false, 'derivada' => true, 'frases' => []],

        'mareo' => ['etiqueta' => 'Mareo', 'frases' => [
            'mareo*', 'maread*', 'mareand*', 'me marea*', 'me marean', 'vertigo*', 'vertigos', 'me da vueltas', 'da vueltas', 'dando vueltas', 'vueltas',
            'aturdid*', 'aturdimiento', 'inestable', 'inestabilidad', 'cabeza ligera', 'sensacion de mareo',
        ]],
        'falta_aire' => ['etiqueta' => 'Falta de aire', 'frases' => [
            'falta de aire', 'falta el aire', 'falta aire', 'falta de respiracion', 'falta la respiracion', 'falta el aliento', 'me falta aire',
            'no me llega el aire', 'no llega aire', 'no llega el aire', 'no puedo respirar', 'no puedo coger aire', 'no puedo tomar aire', 'no consigo respirar',
            'no logro respirar', 'no respiro bien', 'no respiro', 'respiro mal', 'respiro con dificultad', 'respirar con dificultad', 'me cuesta respirar', 'cuesta respirar',
            'dificultad para respirar', 'dificultad al respirar', 'dificultad respiratoria', 'dificultades para respirar', 'respiracion dificil',
            'respiracion entrecortada', 'respiracion agitada', 'me ahogo', 'ahog*', 'sin aire', 'sin aliento', 'sin respiracion', 'sofoc*', 'asfixi*',
            'disnea', 'jadeo', 'jadeando', 'respiro rapido', 'respiracion rapida', 'respirando rapido',
        ]],
        'nauseas' => ['etiqueta' => 'Náuseas', 'frases' => [
            'nausea*', 'nauseas', 'nauseabund*', 'ganas de vomitar', 'ganas de devolver', 'asco', 'revuelto el estomago', 'estomago revuelto', 'estomago revuelto',
            'me da asco', 'basca*',
        ]],
        'vomito' => ['etiqueta' => 'Vómito', 'frases' => [
            'vomit*', 'vomito', 'vomitos', 'vomitando', 'vomite', 'devuelvo', 'devolviendo', 'devolver', 'arrojo', 'echando todo', 'he devuelto',
        ]],
        'fiebre' => ['etiqueta' => 'Fiebre', 'frases' => [
            'fiebre*', 'febril', 'febricula', 'calentura*', 'temperatura alta', 'destemplad*', 'me arde la frente', 'ardiendo de fiebre',
        ]],
        'escalofrios' => ['etiqueta' => 'Escalofríos', 'frases' => [
            'escalofrio*', 'tiritona*', 'tiritando', 'tiemblo de frio', 'temblor de frio', 'temblores de frio', 'me da frio', 'castaneo',
        ]],
        'fatiga' => ['etiqueta' => 'Fatiga', 'frases' => [
            'fatiga', 'fatigad*', 'cansad*', 'cansancio', 'agotad*', 'agotamiento', 'sin energia', 'sin energias', 'sin ganas', 'decaid*', 'somnolien*',
            'somnolencia', 'adormilad*', 'letargo', 'letargic*', 'mucho sueno', 'sueno excesivo', 'molid*', 'exhaust*',
        ]],
        'palpitaciones' => ['etiqueta' => 'Palpitaciones', 'frases' => [
            'palpitacion*', 'palpitaciones', 'corazon acelerado', 'corazon latiendo', 'corazon late', 'el corazon me late', 'me late el corazon', 'late rapido', 'late muy rapido',
            'late fuerte', 'late muy fuerte', 'corazon se acelera', 'corazon va muy rapido', 'corazon va rapido', 'corazon a mil', 'corazon desbocado', 'taquicardia*',
            'arritmia*', 'latidos fuertes', 'latidos rapidos', 'latidos acelerados', 'latidos irregulares', 'pulso acelerado', 'pulso rapido', 'pulso irregular',
            'me salta el corazon', 'salto del corazon', 'corazon salta', 'corazon me da saltos', 'corazon me golpea',
        ]],
        'desmayo' => ['etiqueta' => 'Desmayo o casi desmayo', 'frases' => [
            'desmay*', 'perdi el conocimiento', 'perdi el sentido', 'perdi la conciencia', 'pierdo el conocimiento', 'pierdo el sentido', 'pierdo la conciencia',
            'perdida de conocimiento', 'perdida del conocimiento', 'perdida de conciencia', 'perdida de la conciencia', 'inconsciente', 'inconsciencia', 'sincope',
            'lipotimia', 'me apague', 'se me apago', 'me quede inconsciente', 'me voy a caer redondo',
        ]],
        'confusion' => ['etiqueta' => 'Confusión', 'frases' => [
            'confus*', 'confund*', 'desorient*', 'no se donde estoy', 'no se que dia es', 'no se quien soy', 'no recuerdo nada', 'no recuerdo donde', 'mente en blanco',
            'pensamiento confuso', 'dificultad para pensar', 'me cuesta pensar', 'no puedo pensar', 'pensar con claridad',
        ]],
        // Alteración aguda del habla: se trata como 'confusion' en la bandera roja (supuesto conservador, ver 'banderas').
        'dificultad_habla' => ['etiqueta' => 'Dificultad para hablar', 'frases' => [
            'dificultad para hablar', 'dificultad al hablar', 'no puedo hablar', 'habla arrastrada', 'habla confusa', 'arrastro las palabras', 'se me traba la lengua',
            'lengua trabada', 'no puedo articular', 'balbuce*', 'no hablo bien', 'hablo raro', 'hablo mal', 'dificultad para articular', 'dificultad para expresarme',
        ]],
        'vision' => ['etiqueta' => 'Alteración de la visión', 'frases' => [
            'vision borrosa', 'veo borroso', 'veo borrosa', 'veo doble', 'vision doble', 'doble vision', 'vision nublada', 'vista borrosa', 'vista nublada', 'no veo bien',
            'veo manchas', 'veo puntos', 'veo luces', 'destellos', 'se me nubla la vista', 'se me nublo la vista', 'se me va la vista', 'problemas de vision',
            'problemas de la vista', 'alteracion visual', 'alteraciones visuales', 'ver borroso', 'ver doble', 'vision turbia', 'vision en tunel', 'ver puntos',
            'veo mal', 'veo nublado', 'veo turbio', 'borros*',
        ]],
        'perdida_vision' => ['etiqueta' => 'Pérdida de visión', 'frases' => [
            'perdi la vision', 'perdi la vista', 'pierdo la vision', 'pierdo la vista', 'perdida de vision', 'perdida de la vision', 'perdida de vista', 'perdida visual',
            'ceguera', 'ciego', 'ciega', 'me quede ciego', 'me quede ciega', 'no veo nada', 'no veo por', 'no veo con', 'no veo de', 'veo negro', 'todo negro',
            'vision negra', 'sin vision', 'se me fue la vista', 'se fue la vista', 'no puedo ver',
        ]],
        'debilidad' => ['etiqueta' => 'Debilidad', 'region' => true, 'frases' => [
            'debil*', 'sin fuerza', 'sin fuerzas', 'no tengo fuerza', 'no tengo fuerzas', 'pierdo fuerza', 'perdi fuerza', 'perdida de fuerza', 'flojedad',
            'paralisis', 'paralizad*', 'no puedo mover', 'no puedo levantar', 'no muevo', 'no siento fuerza',
        ]],
        // Hallazgos que por sí mismos indican debilidad de un lado (cara caída...).
        'debilidad_lado' => ['etiqueta' => 'Debilidad de un lado', 'frases' => [
            'cara caida', 'cara torcida', 'boca torcida', 'se me cae la cara', 'se me cae la boca', 'cara dormida',
        ]],
        'entumecimiento' => ['etiqueta' => 'Entumecimiento u hormigueo', 'region' => true, 'frases' => [
            'entumecimiento', 'entumecid*', 'entumec*', 'adormecimiento', 'adormecid*', 'hormigue*', 'cosquilleo', 'sin sensibilidad', 'pierdo sensibilidad',
            'perdida de sensibilidad', 'no tengo sensibilidad', 'se me duerm*', 'se me durmi*', 'se me ha dormido', 'se me han dormido', 'tengo dormid*',
            ['dormid*', 'requiere_region' => true],
        ]],
        'hemorragia' => ['etiqueta' => 'Sangrado', 'region' => true, 'frases' => [
            ['hemorrag*', 'importante' => true], ['mucha sangre', 'importante' => true], ['mucho sangrado', 'importante' => true],
            'sangr*', 'sangre',
        ]],
        'convulsion' => ['etiqueta' => 'Convulsión', 'frases' => [
            'convuls*', 'ataque epileptico', 'crisis epileptica', 'ataque de epilepsia', 'sacudidas incontrolables', 'temblores incontrolables',
            'espasmos incontrolables', 'me sacudo', 'ataque convulsivo', 'epileptic*',
        ]],
        'sudoracion' => ['etiqueta' => 'Sudoración', 'frases' => [
            'sudor frio', 'sudores frios', 'sudoracion*', 'sudo mucho', 'sudando', 'sudo frio', 'sudo', 'sudor*',
        ]],
        'tos' => ['etiqueta' => 'Tos', 'frases' => ['tos', 'tose', 'toso', 'tosiendo', 'toser', 'tos seca']],
        'diarrea' => ['etiqueta' => 'Diarrea', 'frases' => ['diarrea*', 'deposiciones liquidas', 'heces liquidas']],
        'insomnio' => ['etiqueta' => 'Problemas para dormir', 'frases' => [
            'insomnio', 'no puedo dormir', 'no duermo', 'no he dormido', 'duermo mal', 'dormi mal', 'no logro dormir', 'sin dormir',
        ]],
        'hinchazon' => ['etiqueta' => 'Hinchazón', 'region' => true, 'frases' => ['hinchazon', 'hinchad*', 'inflamad*', 'inflamacion', 'edema', 'hincha', 'inflamo']],
        'rigidez' => ['etiqueta' => 'Rigidez', 'region' => true, 'frases' => ['rigidez', 'rigid*', 'agarrotad*', 'agarrotamiento', 'tension muscular']],
    ],

    // Claves que son sinónimo de otra a efectos de reglas (el chip conserva su clave).
    'claves_derivadas' => ['dolor_cabeza', 'presion_pecho'],

    // ---------------------------------------------------------------- regiones (canon => etiqueta, género, frases)
    // Coherentes con las zonas de la silueta (MusculosCuerpo) y con config/panel.php. 'genero' f/m solo concuerda "izquierda/izquierdo".
    'regiones' => [
        'cabeza' => ['etiqueta' => 'cabeza', 'genero' => 'f', 'frases' => ['cabeza', 'craneo', 'sien', 'sienes', 'frente', 'coronilla', 'cuero cabelludo']],
        'cara' => ['etiqueta' => 'cara', 'genero' => 'f', 'frases' => ['cara', 'mejilla', 'mejillas']],
        'mandibula' => ['etiqueta' => 'mandíbula', 'genero' => 'f', 'frases' => ['mandibula', 'mandibulas', 'quijada', 'maxilar']],
        'ojo' => ['etiqueta' => 'ojo', 'genero' => 'm', 'frases' => ['ojo', 'ojos', 'ocular']],
        'oido' => ['etiqueta' => 'oído', 'genero' => 'm', 'frases' => ['oido', 'oidos', 'oreja', 'orejas']],
        'nariz' => ['etiqueta' => 'nariz', 'genero' => 'f', 'frases' => ['nariz', 'fosas nasales']],
        'boca' => ['etiqueta' => 'boca', 'genero' => 'f', 'frases' => ['boca', 'muela', 'muelas', 'diente', 'dientes', 'encia', 'encias', 'lengua', 'labio', 'labios']],
        'garganta' => ['etiqueta' => 'garganta', 'genero' => 'f', 'frases' => ['garganta', 'amigdalas']],
        'cuello' => ['etiqueta' => 'cuello', 'genero' => 'm', 'frases' => ['cuello', 'nuca', 'cervical', 'cervicales', 'esternocleidomastoideo']],
        'hombro' => ['etiqueta' => 'hombro', 'genero' => 'm', 'frases' => ['hombro', 'hombros', 'deltoides', 'trapecio', 'trapecios']],
        'brazo' => ['etiqueta' => 'brazo', 'genero' => 'm', 'frases' => ['brazo', 'brazos', 'miembro superior']],
        'biceps' => ['etiqueta' => 'bíceps', 'genero' => 'm', 'frases' => ['biceps', 'bicep']],
        'triceps' => ['etiqueta' => 'tríceps', 'genero' => 'm', 'frases' => ['triceps', 'tricep']],
        'codo' => ['etiqueta' => 'codo', 'genero' => 'm', 'frases' => ['codo', 'codos']],
        'antebrazo' => ['etiqueta' => 'antebrazo', 'genero' => 'm', 'frases' => ['antebrazo', 'antebrazos']],
        'muneca' => ['etiqueta' => 'muñeca', 'genero' => 'f', 'frases' => ['muneca', 'munecas']],
        'mano' => ['etiqueta' => 'mano', 'genero' => 'f', 'frases' => ['mano', 'manos']],
        'dedo' => ['etiqueta' => 'dedo', 'genero' => 'm', 'frases' => ['dedo', 'dedos', 'pulgar']],
        'pecho' => ['etiqueta' => 'pecho', 'genero' => 'm', 'frases' => [
            'pecho', 'pechos', 'torax', 'toracico', 'toracica', 'esternon', 'caja toracica', 'pectoral', 'pectorales', 'corazon', 'region del corazon', 'centro del pecho',
        ]],
        'costillas' => ['etiqueta' => 'costillas', 'genero' => 'f', 'frases' => ['costilla', 'costillas']],
        'costado' => ['etiqueta' => 'costado', 'genero' => 'm', 'frases' => ['costado', 'costados', 'flanco']],
        'espalda' => ['etiqueta' => 'espalda', 'genero' => 'f', 'frases' => ['espalda', 'columna', 'dorsal', 'dorsales', 'dorsal ancho']],
        'espalda alta' => ['etiqueta' => 'espalda alta', 'genero' => 'f', 'frases' => ['espalda alta', 'parte alta de la espalda', 'omoplato', 'omoplatos', 'escapula']],
        'espalda baja' => ['etiqueta' => 'espalda baja', 'genero' => 'f', 'frases' => [
            'espalda baja', 'parte baja de la espalda', 'lumbar', 'lumbares', 'zona lumbar', 'region lumbar', 'rinon', 'rinones',
        ]],
        'abdomen' => ['etiqueta' => 'abdomen', 'genero' => 'm', 'frases' => [
            'abdomen', 'abdominal', 'abdominales', 'estomago', 'barriga', 'vientre', 'panza', 'tripa', 'ombligo', 'boca del estomago', 'oblicuo', 'oblicuos',
        ]],
        'ingle' => ['etiqueta' => 'ingle', 'genero' => 'f', 'frases' => ['ingle', 'ingles']],
        'cadera' => ['etiqueta' => 'cadera', 'genero' => 'f', 'frases' => ['cadera', 'caderas']],
        'gluteo' => ['etiqueta' => 'glúteo', 'genero' => 'm', 'frases' => ['gluteo', 'gluteos', 'nalga', 'nalgas']],
        'muslo' => ['etiqueta' => 'muslo', 'genero' => 'm', 'frases' => ['muslo', 'muslos', 'cuadriceps', 'femoral', 'isquiotibiales']],
        'pierna' => ['etiqueta' => 'pierna', 'genero' => 'f', 'frases' => ['pierna', 'piernas', 'miembro inferior']],
        'rodilla' => ['etiqueta' => 'rodilla', 'genero' => 'f', 'frases' => ['rodilla', 'rodillas']],
        'pantorrilla' => ['etiqueta' => 'pantorrilla', 'genero' => 'f', 'frases' => ['pantorrilla', 'pantorrillas', 'gemelo', 'gemelos', 'soleo']],
        'tobillo' => ['etiqueta' => 'tobillo', 'genero' => 'm', 'frases' => ['tobillo', 'tobillos']],
        'pie' => ['etiqueta' => 'pie', 'genero' => 'm', 'frases' => ['pie', 'pies', 'talon', 'talones', 'planta', 'plantas', 'empeine', 'dedo del pie', 'dedos del pie']],
        'extremidad' => ['etiqueta' => 'extremidades', 'genero' => 'f', 'frases' => ['extremidad', 'extremidades', 'miembro', 'miembros']],
        'musculo' => ['etiqueta' => 'músculo', 'genero' => 'm', 'frases' => ['musculo', 'musculos', 'muscular', 'musculares']],
        'articulacion' => ['etiqueta' => 'articulaciones', 'genero' => 'f', 'frases' => ['articulacion', 'articulaciones', 'articular']],
        'hueso' => ['etiqueta' => 'huesos', 'genero' => 'm', 'frases' => ['hueso', 'huesos', 'oseo', 'oseos']],
        'cuerpo' => ['etiqueta' => 'todo el cuerpo', 'genero' => 'm', 'frases' => ['todo el cuerpo', 'cuerpo entero', 'cuerpo', 'generalizado', 'generalizada']],
        // "un lado del cuerpo": la bandera de debilidad de un lado la usa sin necesitar izquierdo/derecho.
        'un_lado' => ['etiqueta' => 'un lado del cuerpo', 'genero' => 'm', 'frases' => [
            'un lado del cuerpo', 'un lado de la cara', 'un lado', 'la mitad del cuerpo', 'mitad del cuerpo', 'medio cuerpo', 'hemicuerpo', 'mitad de la cara',
        ]],
    ],

    // Regiones torácicas (dolor o presión aquí = bandera dolor_toracico).
    'regiones_toracicas' => ['pecho'],

    // ---------------------------------------------------------------- lateralidad
    'lados' => [
        'izquierdo' => ['izquierdo', 'izquierda', 'izquierdos', 'izquierdas', 'izquierd*', 'izq', 'izqdo', 'izqda', 'izqd'],
        'derecho' => ['derecho', 'derecha', 'derechos', 'derechas', 'derech*', 'der', 'dcho', 'dcha'],
    ],
    // "ambos brazos", "las dos piernas": se expande en izquierdo y derecho.
    'ambos' => ['ambos', 'ambas', 'los dos', 'las dos', 'los 2', 'las 2', 'bilateral', 'bilaterales'],

    // ---------------------------------------------------------------- calidad del dolor
    'calidades' => [
        'punzante' => ['punzante', 'punzantes', 'lancinante', 'agud*', 'como una aguja', 'como agujas', 'como un cuchillo', 'como una puñalada', 'como una punalada', 'cuchillada', 'agujas'],
        'opresivo' => ['opresivo', 'opresiva', 'opresivos', 'que oprime', 'que aprieta'],
        'quemante' => ['quemante', 'quemantes', 'ardiente', 'que quema', 'que arde', 'como fuego', 'como si quemara', 'urente'],
        'sordo' => ['sordo', 'sorda', 'sordos', 'apagado', 'apagada', 'molestia sorda'],
        'pulsatil' => ['pulsatil', 'pulsatiles', 'pulsante', 'palpitante', 'como latidos', 'que late', 'que pulsa', 'latiendo', 'pulsando'],
        'colico' => ['tipo colico', 'de colico', 'como un colico', 'tipo retortijon', 'colicos'],
        'calambre' => ['tipo calambre', 'como un calambre', 'de calambre'],
        'rigidez' => ['con rigidez', 'tipo rigidez'],
    ],

    // ---------------------------------------------------------------- intensidad por palabra (valor 1-10 => frases)
    // Alineado con ZonaDolor::palabra (<=3 leve, <=6 moderado, <=8 fuerte, >8 muy fuerte). Supuesto del proyecto.
    'intensidad_palabras' => [
        2 => ['leve', 'leves', 'ligero', 'ligera', 'ligeros', 'suave', 'suaves', 'poco', 'poca', 'poquito', 'poquita', 'un poco', 'minimo', 'minima', 'pequeno', 'pequena', 'pequenito', 'tenue'],
        5 => ['moderado', 'moderada', 'moderados', 'bastante', 'considerable', 'soportable'],
        7 => ['fuerte', 'fuertes', 'intenso', 'intensa', 'intensos', 'intensas', 'mucho', 'mucha', 'gran', 'severo', 'severa', 'serio', 'seria', 'importante'],
        9 => ['muy fuerte', 'muy fuertes', 'muy intenso', 'muy intensa', 'muy severo', 'muy severa', 'tremendo', 'tremenda', 'terrible', 'horrible', 'atroz', 'brutal', 'espantoso', 'espantosa', 'enorme', 'fortisimo'],
        10 => ['insoportable', 'insoportables', 'inaguantable', 'no aguanto', 'no soporto', 'maximo'],
    ],

    // ---------------------------------------------------------------- conectores y cues
    'negaciones' => ['no', 'sin', 'nunca', 'tampoco', 'ni', 'nada de', 'ningun*', 'ninguna', 'descarto', 'niego'],
    // Cortan el alcance de la negación y separan oraciones.
    'cortes' => ['pero', 'aunque', 'sin embargo', 'excepto', 'salvo', 'eso si'],
    // Aparición súbita (se usa solo para el dolor de cabeza súbito e intenso). 'intensidad' => también cuenta como esa intensidad.
    'subito' => [
        'de repente', 'de golpe', 'de pronto', 'de improviso', 'subito', 'subita', 'subitamente', 'repentino', 'repentina', 'repentinamente', 'brusco', 'brusca', 'bruscamente',
        'instantaneo', 'instantanea', 'inesperado', 'inesperada', 'como un trueno', 'como un golpe', 'como un martillazo', 'explosivo', 'explosiva', 'explota', 'estalla',
        'me exploto', 'se me exploto',
    ],
    // Persistencia (vómito) o gravedad (hemorragia).
    'grave' => [
        'no para de', 'no deja de', 'no dejo de', 'no paro de', 'no he parado de', 'no puedo parar de', 'sin parar', 'sin cesar', 'varias veces', 'muchas veces', 'repetidamente', 'repetid*',
        'persistente', 'persistentes', 'continuo', 'continuos', 'continua', 'continuas', 'todo el rato', 'una y otra vez', 'constante', 'constantemente', 'seguidos', 'seguidas',
        'a cada rato', 'cada rato', 'vez tras vez',
        'abundante', 'abundantes', 'profus*', 'copios*', 'a chorros', 'chorro', 'no para', 'no se detiene', 'no se corta', 'no cesa', 'no deja de sangrar', 'no puedo detener', 'no puedo parar',
        'empapad*', 'charco', 'cantidad de sangre',
    ],

    // ---------------------------------------------------------------- banderas rojas
    // Orientación general y conservadora, NO un protocolo validado (revisión médica pendiente). Ante la duda se escala.
    // Cada bandera: 'descripcion' y 'reglas'. Una regla se cumple si algún síntoma (no negado) cumple TODAS sus condiciones:
    //   'claves' (lista), 'regiones' (la región debe estar en la lista; 'null' admite región vacía), 'exige_lado' (izquierdo/derecho
    //   o región 'un_lado'), 'exige' (campos verdaderos: subito, persistente, importante), 'intensidad_min'.
    'banderas' => [
        'dolor_toracico' => ['descripcion' => 'Dolor o presión en el pecho', 'reglas' => [
            ['claves' => ['presion_pecho']],
            ['claves' => ['dolor'], 'regiones' => ['pecho']],
        ]],
        'falta_aire' => ['descripcion' => 'Dificultad para respirar o falta de aire', 'reglas' => [['claves' => ['falta_aire']]]],
        'desmayo' => ['descripcion' => 'Desmayo o casi desmayo', 'reglas' => [['claves' => ['desmayo']]]],
        'dolor_cabeza_subito_intenso' => ['descripcion' => 'Dolor de cabeza súbito e intenso', 'reglas' => [
            ['claves' => ['dolor_cabeza'], 'exige' => ['subito'], 'intensidad_min' => 7],
        ]],
        'confusion' => ['descripcion' => 'Confusión o alteración del habla', 'reglas' => [['claves' => ['confusion', 'dificultad_habla']]]],
        'debilidad_lado' => ['descripcion' => 'Debilidad o entumecimiento de un lado del cuerpo', 'reglas' => [
            ['claves' => ['debilidad_lado']],
            // Supuesto: extremidad, cara o cuerpo con lado conocido; mano o pie solos (hormigueo común) no la activan.
            ['claves' => ['debilidad', 'entumecimiento'], 'exige_lado' => true, 'regiones' => [null, 'brazo', 'pierna', 'cara', 'cuerpo', 'un_lado', 'extremidad', 'biceps', 'triceps', 'antebrazo', 'muslo']],
        ]],
        'perdida_vision' => ['descripcion' => 'Pérdida de visión', 'reglas' => [['claves' => ['perdida_vision']]]],
        'hemorragia_importante' => ['descripcion' => 'Hemorragia importante', 'reglas' => [['claves' => ['hemorragia'], 'exige' => ['importante']]]],
        'vomito_persistente' => ['descripcion' => 'Vómito persistente', 'reglas' => [['claves' => ['vomito'], 'exige' => ['persistente']]]],
        'convulsion' => ['descripcion' => 'Convulsión', 'reglas' => [['claves' => ['convulsion']]]],
    ],
];
