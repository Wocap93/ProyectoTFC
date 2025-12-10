-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 10-12-2025 a las 22:15:48
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `FURRI_CUARTEL`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `asignar_material_con_stock` (IN `p_id_personal` INT, IN `p_id_material` INT)   BEGIN
    DECLARE stock_actual INT;

    START TRANSACTION;

    -- Verificar stock
    SELECT stock_total INTO stock_actual
    FROM MATERIALES
    WHERE id_material = p_id_material
    FOR UPDATE;

    IF stock_actual <= 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '❌ No queda stock disponible para este material.';
    ELSE
        -- Insertar la entrega
        INSERT INTO ENTREGAS_INDIVIDUALES (id_personal, id_material)
        VALUES (p_id_personal, p_id_material);

        -- Restar stock
        UPDATE MATERIALES
        SET stock_total = stock_total - 1
        WHERE id_material = p_id_material;

        COMMIT;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ARMAMENTO_COLECTIVO`
--

CREATE TABLE `ARMAMENTO_COLECTIVO` (
  `id_armamento` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `estado` enum('operativo','inoperativo','escalón') DEFAULT 'operativo',
  `asignado_a` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ARMAMENTO_COLECTIVO`
--

INSERT INTO `ARMAMENTO_COLECTIVO` (`id_armamento`, `nombre`, `numero_serie`, `estado`, `asignado_a`) VALUES
(1, 'Ametralladora MG4', 'MG4-1001', 'operativo', '1ª Sección'),
(2, 'Ametralladora MG4', 'MG4-1002', 'operativo', '2ª Sección');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ARMAMENTO_INDIVIDUAL`
--

CREATE TABLE `ARMAMENTO_INDIVIDUAL` (
  `id_arma` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `estado` enum('operativo','inoperativo','escalón') DEFAULT 'operativo',
  `tipo` enum('fusil','pistola','otro') NOT NULL DEFAULT 'fusil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ARMAMENTO_INDIVIDUAL`
--

INSERT INTO `ARMAMENTO_INDIVIDUAL` (`id_arma`, `nombre`, `numero_serie`, `estado`, `tipo`) VALUES
(1, 'HK G36E', 'G36E-00123', 'operativo', 'fusil'),
(2, 'HK G36E', 'G36E-00124', 'operativo', 'fusil'),
(3, 'Pistola HK USP', 'USP-00056', 'operativo', 'pistola'),
(4, 'Pistola HK USP', 'USP-00057', 'operativo', 'pistola'),
(5, 'HK G36E', 'G36E-00125', 'operativo', 'fusil');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ASIGNACION_COLECTIVO`
--

CREATE TABLE `ASIGNACION_COLECTIVO` (
  `id_armamento` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `fecha_asignacion` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ASIGNACION_COLECTIVO`
--

INSERT INTO `ASIGNACION_COLECTIVO` (`id_armamento`, `id_personal`, `fecha_asignacion`) VALUES
(1, 1, '2025-06-10'),
(2, 6, '2025-07-04'),
(2, 8, '2025-07-04');

--
-- Disparadores `ASIGNACION_COLECTIVO`
--
DELIMITER $$
CREATE TRIGGER `limitar_dos_militares_por_armamento` BEFORE INSERT ON `ASIGNACION_COLECTIVO` FOR EACH ROW BEGIN
    DECLARE total INT;

    SELECT COUNT(*) INTO total
    FROM ASIGNACION_COLECTIVO
    WHERE id_armamento = NEW.id_armamento;

    IF total >= 2 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este armamento ya tiene el máximo de 2 militares asignados.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ASIGNACION_INDIVIDUAL`
--

CREATE TABLE `ASIGNACION_INDIVIDUAL` (
  `id_asignacion` int(11) NOT NULL,
  `id_arma` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `fecha_asignacion` date DEFAULT curdate(),
  `fecha_devolucion` date DEFAULT NULL,
  `estado` enum('asignado','devuelto','extraviado','reparación') DEFAULT 'asignado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ASIGNACION_INDIVIDUAL`
--

INSERT INTO `ASIGNACION_INDIVIDUAL` (`id_asignacion`, `id_arma`, `id_personal`, `fecha_asignacion`, `fecha_devolucion`, `estado`) VALUES
(1, 1, 1, '2025-06-01', NULL, 'asignado'),
(6, 2, 7, '2025-06-29', NULL, 'asignado'),
(7, 4, 7, '2025-06-29', NULL, 'asignado');

--
-- Disparadores `ASIGNACION_INDIVIDUAL`
--
DELIMITER $$
CREATE TRIGGER `limitar_pistolas_por_empleo` BEFORE INSERT ON `ASIGNACION_INDIVIDUAL` FOR EACH ROW BEGIN
    DECLARE tipo_arma VARCHAR(20);
    DECLARE id_empleo INT;

    -- Obtener tipo de arma
    SELECT tipo INTO tipo_arma
    FROM ARMAMENTO_INDIVIDUAL
    WHERE id_arma = NEW.id_arma;

    -- Obtener empleo del militar
    SELECT empleo_id INTO id_empleo
    FROM PERSONAS
    WHERE id_personal = NEW.id_personal;

    -- Si es pistola y el empleo está restringido (1: Soldado, 2: Cabo, 3: Cabo Primero, 4: Cabo Mayor)
    IF tipo_arma = 'pistola' AND id_empleo IN (1, 2, 3, 4) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '❌ Este empleo no tiene permiso para recibir pistolas.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `validar_asignacion_unica_por_tipo` BEFORE INSERT ON `ASIGNACION_INDIVIDUAL` FOR EACH ROW BEGIN
    DECLARE tipo_arma ENUM('fusil', 'pistola');
    DECLARE mensaje_error VARCHAR(100);

    -- Obtener tipo del arma que se quiere asignar
    SELECT tipo INTO tipo_arma
    FROM ARMAMENTO_INDIVIDUAL
    WHERE id_arma = NEW.id_arma;

    -- Verificar si ya tiene una asignación activa del mismo tipo
    IF EXISTS (
        SELECT 1
        FROM ASIGNACION_INDIVIDUAL ai
        JOIN ARMAMENTO_INDIVIDUAL ar ON ai.id_arma = ar.id_arma
        WHERE ai.id_personal = NEW.id_personal
        AND ai.fecha_devolucion IS NULL
        AND ar.tipo = tipo_arma
    ) THEN
        SET mensaje_error = CONCAT('❌ Ya tiene un arma tipo ', tipo_arma, ' asignada.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = mensaje_error;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `EMPLEOS`
--

CREATE TABLE `EMPLEOS` (
  `id_empleo` int(11) NOT NULL,
  `nombre_empleo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `EMPLEOS`
--

INSERT INTO `EMPLEOS` (`id_empleo`, `nombre_empleo`) VALUES
(1, 'Soldado'),
(2, 'Cabo'),
(3, 'Cabo Primero'),
(4, 'Cabo Mayor'),
(5, 'Sargento'),
(6, 'Sargento Primero'),
(7, 'Brigada'),
(8, 'Subteniente'),
(9, 'Suboficial Mayor'),
(10, 'Teniente'),
(11, 'Capitán'),
(12, 'Comandante'),
(13, 'Teniente Coronel'),
(14, 'Coronel');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ENTREGAS_INDIVIDUALES`
--

CREATE TABLE `ENTREGAS_INDIVIDUALES` (
  `id_entrega` int(11) NOT NULL,
  `id_personal` int(11) DEFAULT NULL,
  `id_material` int(11) DEFAULT NULL,
  `fecha_entrega` date DEFAULT curdate(),
  `fecha_devolucion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ENTREGAS_INDIVIDUALES`
--

INSERT INTO `ENTREGAS_INDIVIDUALES` (`id_entrega`, `id_personal`, `id_material`, `fecha_entrega`, `fecha_devolucion`) VALUES
(1, 1, 1, '2025-06-01', NULL),
(2, 1, 2, '2025-06-01', NULL),
(6, 7, 4, '2025-06-29', NULL),
(7, 7, 2, '2025-06-29', NULL),
(15, 7, 5, '2025-06-29', NULL),
(16, 7, 7, '2025-06-29', NULL),
(17, 7, 1, '2025-06-29', NULL),
(18, 7, 3, '2025-06-29', NULL),
(19, 7, 6, '2025-06-29', NULL),
(20, 8, 4, '2025-07-04', NULL),
(21, 8, 5, '2025-07-04', NULL),
(22, 8, 7, '2025-07-04', NULL),
(23, 8, 2, '2025-07-04', NULL),
(24, 8, 1, '2025-07-04', NULL),
(25, 8, 3, '2025-07-04', NULL),
(26, 8, 6, '2025-07-04', NULL),
(27, 1, 4, '2025-07-04', NULL),
(28, 1, 5, '2025-07-04', NULL),
(29, 1, 7, '2025-07-04', NULL),
(30, 1, 3, '2025-07-04', NULL),
(31, 1, 6, '2025-07-04', NULL),
(32, 6, 4, '2025-07-04', NULL),
(33, 6, 5, '2025-07-04', NULL),
(34, 6, 7, '2025-07-04', NULL),
(35, 6, 2, '2025-07-04', NULL),
(36, 6, 1, '2025-07-04', NULL),
(37, 6, 3, '2025-07-04', NULL),
(38, 6, 6, '2025-07-04', NULL);

--
-- Disparadores `ENTREGAS_INDIVIDUALES`
--
DELIMITER $$
CREATE TRIGGER `evitar_material_duplicado` BEFORE INSERT ON `ENTREGAS_INDIVIDUALES` FOR EACH ROW BEGIN
    DECLARE ya_tiene INT;

    SELECT COUNT(*) INTO ya_tiene
    FROM ENTREGAS_INDIVIDUALES
    WHERE id_personal = NEW.id_personal
      AND id_material = NEW.id_material
      AND fecha_devolucion IS NULL;

    IF ya_tiene > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '❌ Este militar ya tiene este material asignado sin devolver.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `sumar_stock_al_devolver` AFTER UPDATE ON `ENTREGAS_INDIVIDUALES` FOR EACH ROW BEGIN
    -- Solo sumar si antes estaba sin devolver y ahora se ha devuelto
    IF OLD.fecha_devolucion IS NULL AND NEW.fecha_devolucion IS NOT NULL THEN
        UPDATE MATERIALES
        SET stock_total = stock_total + 1
        WHERE id_material = NEW.id_material;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `MATERIALES`
--

CREATE TABLE `MATERIALES` (
  `id_material` int(11) NOT NULL,
  `nombre_material` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `stock_total` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `MATERIALES`
--

INSERT INTO `MATERIALES` (`id_material`, `nombre_material`, `descripcion`, `stock_total`) VALUES
(1, 'Mochila de combate', 'Mochila táctica estándar para patrullas', 47),
(2, 'Mochila Altus', 'Mochila para llevar todo el material', 47),
(3, 'Tienda individual', 'Tienda de campaña individual de lona', 47),
(4, 'Casco', 'Casco para el combate', 44),
(5, 'Chaleco', 'Chaleco de combate nuevo', 47),
(6, 'Zapapico', 'Herramienta individual', 16),
(7, 'Esterilla', 'Aislante para el suelo', 46);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `NFC_EPHEMERAL`
--

CREATE TABLE `NFC_EPHEMERAL` (
  `token` varchar(64) NOT NULL,
  `uid` varchar(64) NOT NULL,
  `ldap_uid` varchar(128) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `NFC_EPHEMERAL`
--

INSERT INTO `NFC_EPHEMERAL` (`token`, `uid`, `ldap_uid`, `expires_at`, `used`, `created_at`) VALUES
('02aae1de6c4bbd754696f406adef0e3a', '515c9c1e', 'fgarrod', '2025-12-09 20:36:30', 0, '2025-12-09 19:36:15'),
('02b82a55c129bec7fc8c91b4573442b1', 'c92f9d1e', 'ngalalv', '2025-10-21 22:24:11', 1, '2025-10-21 20:23:56'),
('05f6c933201deed2add3b5332bdc92d1', '6e249d1e', 'aleoveg', '2025-10-21 22:38:17', 0, '2025-10-21 20:38:02'),
('074ae7af651af319b7f7fa6fac8e1eda', 'c92f9d1e', 'ngalalv', '2025-10-21 22:47:50', 1, '2025-10-21 20:47:35'),
('08b5c6cda1b15c3f719859b819a90837', '515c9c1e', 'fgarrod', '2025-10-21 22:37:01', 0, '2025-10-21 20:36:46'),
('0a9f649a2a82a6d104b7bd28d1ec2f25', 'c92f9d1e', 'ngalalv', '2025-10-21 22:04:18', 0, '2025-10-21 20:04:03'),
('0e72525ba8436df5b4f458413761347f', '6e249d1e', 'aleoveg', '2025-11-20 22:58:54', 0, '2025-11-20 21:58:39'),
('0e8c95e06826694969fbc07389f11c76', '6e249d1e', 'aleoveg', '2025-10-21 22:47:28', 1, '2025-10-21 20:47:13'),
('0f86192decede524825be4d14027ceb6', 'f33e9d1e', NULL, '2025-10-21 22:40:34', 0, '2025-10-21 20:40:19'),
('0fb547af1ee5b7e20c5aa581b42fad50', '515c9c1e', 'fgarrod', '2025-10-21 17:12:46', 1, '2025-10-21 15:12:31'),
('108a348deb403854fd7abb5a003307e0', '6e249d1e', 'aleoveg', '2025-10-21 22:38:12', 1, '2025-10-21 20:37:57'),
('173f632a2c8a0f543b43be57f7557770', '515c9c1e', 'fgarrod', '2025-10-21 22:23:00', 1, '2025-10-21 20:22:45'),
('1b1a4e95984f14ce770041e98f071645', 'c92f9d1e', 'ngalalv', '2025-10-21 22:25:03', 0, '2025-10-21 20:24:48'),
('1c9f8df6bef5afe758b4efd25fbbcc9a', '6e249d1e', 'aleoveg', '2025-10-21 21:58:09', 0, '2025-10-21 19:57:54'),
('1d29a8469c23fee9f91aa307f9c065d4', 'c92f9d1e', 'ngalalv', '2025-10-21 22:39:52', 0, '2025-10-21 20:39:37'),
('1d3cc1f41b4c4f03eab770b5d2b8febd', 'c92f9d1e', 'ngalalv', '2025-10-21 22:04:15', 1, '2025-10-21 20:04:00'),
('2230e8a5e07851cce243088668571243', '515c9c1e', 'fgarrod', '2025-10-21 17:24:18', 0, '2025-10-21 15:24:03'),
('240e5bfb9f00d2659ea2555de55dbb66', '515c9c1e', 'fgarrod', '2025-10-21 17:25:00', 0, '2025-10-21 15:24:45'),
('242357af7e52a97933077799880844f4', '6e249d1e', 'aleoveg', '2025-12-10 19:48:52', 1, '2025-12-10 18:48:37'),
('260b121b4b26d5018f488854f7dbe03d', '6e249d1e', 'aleoveg', '2025-11-20 22:58:54', 1, '2025-11-20 21:58:39'),
('27d584b88037a3b3ecf897a986dd1cc4', '515c9c1e', 'fgarrod', '2025-10-21 21:40:16', 1, '2025-10-21 19:40:01'),
('2cd5ea6d6a4787e0ea91d33e27c700f2', '515c9c1e', 'fgarrod', '2025-10-21 20:43:42', 1, '2025-10-21 18:43:27'),
('2d2b5b31b5b2e462e051109e17b670d7', '6e249d1e', 'aleoveg', '2025-10-21 21:59:29', 1, '2025-10-21 19:59:14'),
('330ce63d85a6303216f5b11e897a90d5', '515c9c1e', 'fgarrod', '2025-10-21 22:40:54', 0, '2025-10-21 20:40:39'),
('35056eec31249d9cc7cc44544d46353e', '6e249d1e', 'aleoveg', '2025-10-21 21:58:19', 1, '2025-10-21 19:58:04'),
('351715954d18d6dd2cd6d9e56fc3b3b0', '515c9c1e', 'fgarrod', '2025-10-21 21:39:58', 0, '2025-10-21 19:39:43'),
('3bc56411e802e11ccfa098fd29dfd9ba', '6e249d1e', 'aleoveg', '2025-10-21 22:41:05', 0, '2025-10-21 20:40:50'),
('3d032604b12dcf323ecce4a4a2a358b0', 'f33e9d1e', NULL, '2025-11-20 23:17:55', 1, '2025-11-20 22:17:40'),
('3d6a23dd691de1190750cd8788c63d58', 'c92f9d1e', 'ngalalv', '2025-12-10 19:21:23', 1, '2025-12-10 18:21:08'),
('3f57baee387a4a2e77a7ccb3039566ed', '515c9c1e', 'fgarrod', '2025-12-09 20:36:30', 0, '2025-12-09 19:36:15'),
('3f90bca794d278587ea652defc8d771b', '515c9c1e', 'fgarrod', '2025-12-10 19:18:38', 1, '2025-12-10 18:18:23'),
('438202489cb640e14fb90a9d331f4bcd', 'c92f9d1e', 'ngalalv', '2025-12-09 20:39:55', 0, '2025-12-09 19:39:40'),
('48a94097f9560baab3db8338ece01674', 'c92f9d1e', 'ngalalv', '2025-10-21 22:47:49', 0, '2025-10-21 20:47:34'),
('48b9592d9c0febb7de74c5e052163c7b', '515c9c1e', 'fgarrod', '2025-10-21 17:05:35', 0, '2025-10-21 15:05:20'),
('4ddd08a5c071276a9dd32d7ddba28c24', '515c9c1e', 'fgarrod', '2025-10-21 21:38:57', 1, '2025-10-21 19:38:42'),
('539f0b6df5938dd093e57eacf3937dbb', '515c9c1e', 'fgarrod', '2025-12-09 21:07:32', 0, '2025-12-09 20:07:17'),
('584db87bbe5691825e143ae8f568f594', 'c92f9d1e', 'ngalalv', '2025-10-21 22:21:17', 0, '2025-10-21 20:21:02'),
('5a84823166f5fe7a304700ea45222d7b', '6e249d1e', 'aleoveg', '2025-10-21 22:38:12', 0, '2025-10-21 20:37:57'),
('5c233fb7262b19b7407889d795e84d26', '6e249d1e', 'aleoveg', '2025-11-20 22:58:44', 0, '2025-11-20 21:58:29'),
('5d8406b1de8f165bbc27a42d9e721140', '515c9c1e', 'fgarrod', '2025-10-21 21:38:58', 0, '2025-10-21 19:38:43'),
('5dfbbc96394ac191816959465fdcf588', 'f33e9d1e', NULL, '2025-11-20 23:17:55', 0, '2025-11-20 22:17:40'),
('5ebac836febc27eee4d7a4dea2e30be8', '6e249d1e', 'aleoveg', '2025-10-21 21:59:29', 0, '2025-10-21 19:59:14'),
('602b9b5a61ee11342475328939bc7884', '6e249d1e', 'aleoveg', '2025-10-21 22:41:05', 1, '2025-10-21 20:40:50'),
('607d19968cc56c39f9a126904cdbd780', '6e249d1e', 'aleoveg', '2025-10-21 21:58:29', 0, '2025-10-21 19:58:14'),
('6110bfcda49326674f53c5c20566a4e1', '515c9c1e', 'fgarrod', '2025-10-21 17:24:49', 0, '2025-10-21 15:24:34'),
('6271730adcfc116adfcf5bf932645786', '6e249d1e', 'aleoveg', '2025-12-10 19:20:44', 1, '2025-12-10 18:20:29'),
('64b0bada5b2696417edde3b709829715', '515c9c1e', 'fgarrod', '2025-10-21 17:24:42', 0, '2025-10-21 15:24:27'),
('6524075108c6d6d2b3198ae0f8a69e24', '6e249d1e', 'aleoveg', '2025-10-21 22:41:29', 1, '2025-10-21 20:41:14'),
('68c755b31a225f689466b70bcaacadc7', '6e249d1e', 'aleoveg', '2025-10-21 22:38:49', 1, '2025-10-21 20:38:34'),
('696605f694d78a58a2029b49199269b1', 'f33e9d1e', NULL, '2025-10-21 22:36:54', 0, '2025-10-21 20:36:39'),
('6a7a34e29ffd7e94878828fcf23428ad', '515c9c1e', 'fgarrod', '2025-10-21 22:33:28', 0, '2025-10-21 20:33:13'),
('6b228d75ba075154c051c563dc8dd4c3', '6e249d1e', 'aleoveg', '2025-11-25 09:35:19', 1, '2025-11-25 08:35:04'),
('6cc54513504ed60287bbc9b631e56254', 'f33e9d1e', NULL, '2025-11-25 09:35:00', 0, '2025-11-25 08:34:45'),
('6e945d8717b3ae605943d705a8e13be6', 'f33e9d1e', NULL, '2025-10-21 22:36:40', 0, '2025-10-21 20:36:25'),
('734910c930eb99ed798cce1d5d90b1a1', 'f33e9d1e', 'rgonba4', '2025-12-10 19:21:08', 1, '2025-12-10 18:20:53'),
('7791b69818c0abd514ea4eed252edeba', '515c9c1e', 'fgarrod', '2025-10-21 17:04:01', 0, '2025-10-21 15:03:46'),
('796d79c5b43489717cfeb255056a396f', '515c9c1e', 'fgarrod', '2025-10-21 17:26:43', 0, '2025-10-21 15:26:28'),
('7a6c242db47edc1e25b79581c8eaf7cb', '6e249d1e', 'aleoveg', '2025-10-21 21:58:09', 1, '2025-10-21 19:57:54'),
('805fbb3f17290e5e614deef79d1eab0d', '6e249d1e', 'aleoveg', '2025-10-21 22:31:41', 1, '2025-10-21 20:31:26'),
('8123055bd6195b88a743815884edf114', '6e249d1e', 'aleoveg', '2025-10-21 21:59:59', 0, '2025-10-21 19:59:44'),
('82a32862050f6ea0129e5b0124bfa67e', '515c9c1e', 'fgarrod', '2025-10-21 21:41:00', 0, '2025-10-21 19:40:45'),
('82f7431f222a745286dd020e5b3d59ef', 'c92f9d1e', 'ngalalv', '2025-12-09 20:39:55', 0, '2025-12-09 19:39:40'),
('85e1c5373a1a241f7c66af22a2519e35', '515c9c1e', 'fgarrod', '2025-10-21 17:05:34', 1, '2025-10-21 15:05:19'),
('863f6f26078cb587fc37a1add4fc0c85', '515c9c1e', 'fgarrod', '2025-10-21 17:42:01', 0, '2025-10-21 15:41:46'),
('869f09816fcf020c9d3236e87b54f0c3', '515c9c1e', 'fgarrod', '2025-12-10 20:48:14', 1, '2025-12-10 19:47:59'),
('86b50287bf60b388cb4cfd5a8f54340e', '515c9c1e', 'fgarrod', '2025-12-10 20:48:41', 1, '2025-12-10 19:48:26'),
('87ccd9f44ab7a913c37ec7944cdec772', 'f33e9d1e', NULL, '2025-10-21 21:58:42', 1, '2025-10-21 19:58:27'),
('8c2011f093b891a7b5200179901576c2', 'c92f9d1e', 'ngalalv', '2025-10-21 22:39:43', 1, '2025-10-21 20:39:28'),
('8f9949b0ca960b68c3ca2557e16983ea', 'c92f9d1e', 'ngalalv', '2025-11-25 10:04:31', 0, '2025-11-25 09:04:16'),
('94eb283fa86e58e2d661f56a97fe42b6', 'c92f9d1e', 'ngalalv', '2025-12-09 20:34:28', 0, '2025-12-09 19:34:13'),
('965bd2848c5f75eb15dc8c4f1b1b921e', 'f33e9d1e', NULL, '2025-10-21 21:58:05', 1, '2025-10-21 19:57:50'),
('9a8e40eb412198aa33f9250c0b7a1a98', '6e249d1e', 'aleoveg', '2025-10-21 22:32:15', 1, '2025-10-21 20:32:00'),
('9e30b837146d8dcfd037f71c28e975d1', 'c92f9d1e', 'ngalalv', '2025-10-21 22:25:02', 1, '2025-10-21 20:24:47'),
('9e8d3483f3ead2b25615168f62c8cfea', '515c9c1e', 'fgarrod', '2025-10-21 21:40:59', 1, '2025-10-21 19:40:44'),
('a1342b3489d050d0e73d216576bb653e', 'c92f9d1e', 'ngalalv', '2025-11-25 10:05:03', 0, '2025-11-25 09:04:48'),
('a15df75e9ec6ad073d3b0ceeafb40186', '515c9c1e', 'fgarrod', '2025-12-09 21:08:16', 1, '2025-12-09 20:08:01'),
('a1e9493930a4b6e7aed15f91116ffbc8', '6e249d1e', 'aleoveg', '2025-11-20 22:58:42', 0, '2025-11-20 21:58:27'),
('a2dd0533a15517f18b9c7ea89b3271af', '515c9c1e', 'fgarrod', '2025-10-21 17:25:49', 0, '2025-10-21 15:25:34'),
('a704e5ffbb9c39fe73382986ce578add', '515c9c1e', 'fgarrod', '2025-12-09 20:40:10', 0, '2025-12-09 19:39:55'),
('a71a57a376021440fe756ed84c948b4b', 'f33e9d1e', NULL, '2025-11-25 09:35:00', 1, '2025-11-25 08:34:45'),
('a826aad9a98cf1c1c4be1dfc619c4aa7', '6e249d1e', 'aleoveg', '2025-10-21 21:59:31', 0, '2025-10-21 19:59:16'),
('a955985c1ae2af332b9d4941aa419f06', '6e249d1e', 'aleoveg', '2025-10-21 22:38:49', 0, '2025-10-21 20:38:34'),
('a9d9e55df7258c99b9b743bd26c272e2', 'f33e9d1e', NULL, '2025-10-21 21:58:42', 0, '2025-10-21 19:58:27'),
('ae3da6b547e0d8928cd3634e200d30ad', '515c9c1e', 'fgarrod', '2025-10-21 21:40:16', 0, '2025-10-21 19:40:01'),
('ae8454846a0697d60e38b133a74d6d49', 'f33e9d1e', NULL, '2025-10-21 22:40:24', 0, '2025-10-21 20:40:09'),
('ae8d8c646804224ae3b0d35133f9005e', '6e249d1e', 'aleoveg', '2025-10-21 22:47:28', 0, '2025-10-21 20:47:13'),
('aed4aaddf0a5af995947fd4f5992a97f', '6e249d1e', 'aleoveg', '2025-12-10 20:37:18', 1, '2025-12-10 19:37:03'),
('b0f2b450b8b71bdf696550b8e5b6e84f', 'c92f9d1e', 'ngalalv', '2025-11-25 10:04:31', 1, '2025-11-25 09:04:16'),
('b2e8757580230fcb8f8fa98ba00b9cd2', 'c92f9d1e', 'ngalalv', '2025-10-21 22:21:17', 1, '2025-10-21 20:21:02'),
('b4ccf826bc70de08188aac7423c2c127', '515c9c1e', 'fgarrod', '2025-10-21 17:14:21', 1, '2025-10-21 15:14:06'),
('bacb6779975a1df253c30eb7206a6581', 'c92f9d1e', 'ngalalv', '2025-10-21 22:39:44', 0, '2025-10-21 20:39:29'),
('bc54746beb7da2eef2b3607403f14dd0', 'c92f9d1e', 'ngalalv', '2025-10-21 21:54:36', 1, '2025-10-21 19:54:21'),
('bcbe04d369f58c73e19cff33a6ff9b1b', '515c9c1e', 'fgarrod', '2025-10-21 17:14:00', 1, '2025-10-21 15:13:45'),
('bef780b5055e2575be95aa8deaf1c398', '515c9c1e', 'fgarrod', '2025-12-10 19:20:23', 1, '2025-12-10 18:20:08'),
('c056632752f1c1fb4b1c281b5cdd71e1', 'c92f9d1e', 'ngalalv', '2025-10-21 21:55:00', 0, '2025-10-21 19:54:45'),
('c25b85a09edca947928ecc0fb40733f5', 'c92f9d1e', 'ngalalv', '2025-12-10 19:20:53', 1, '2025-12-10 18:20:38'),
('c6ac385ccab52b0721c77fe59ee306db', 'c92f9d1e', 'ngalalv', '2025-10-21 21:54:36', 0, '2025-10-21 19:54:21'),
('c8122a22df4ec8540a78371475e0f610', 'c92f9d1e', 'ngalalv', '2025-12-09 20:34:28', 0, '2025-12-09 19:34:13'),
('c9d37eb627d852fd6360f9fd7916529e', 'f33e9d1e', NULL, '2025-10-21 21:58:05', 0, '2025-10-21 19:57:50'),
('ca2b4bcebe65bf5632d6fbb91c03a9b8', '515c9c1e', 'fgarrod', '2025-10-21 21:40:09', 0, '2025-10-21 19:39:54'),
('ce07189d806d6051119bddadf2278a29', '515c9c1e', 'fgarrod', '2025-12-09 20:40:11', 0, '2025-12-09 19:39:56'),
('ceea771c202273a98053d8d7df5a52ab', '6e249d1e', 'aleoveg', '2025-10-21 22:41:12', 0, '2025-10-21 20:40:57'),
('cf8d7ad2f7c80bc40517b0bfaca324fb', '515c9c1e', 'fgarrod', '2025-10-21 17:13:59', 1, '2025-10-21 15:13:44'),
('d17bc914f1eeba57971935bc448863b7', '515c9c1e', 'fgarrod', '2025-10-21 17:27:38', 1, '2025-10-21 15:27:23'),
('d187f070d42535abd12ec9580372781f', 'c92f9d1e', 'ngalalv', '2025-11-25 10:05:04', 1, '2025-11-25 09:04:49'),
('d191eec838f77b3038ce38b9c2b555f3', '6e249d1e', 'aleoveg', '2025-10-21 22:41:40', 0, '2025-10-21 20:41:25'),
('d53b6f283471005dc23974e37468ff64', '515c9c1e', 'fgarrod', '2025-10-21 21:39:58', 1, '2025-10-21 19:39:43'),
('d876057a0d3b12637c25690462a238c2', '6e249d1e', 'aleoveg', '2025-10-21 22:31:42', 0, '2025-10-21 20:31:27'),
('d915a1e6da7ecffb167788ab833f7e97', '515c9c1e', 'fgarrod', '2025-10-21 17:27:13', 1, '2025-10-21 15:26:58'),
('d9c75b1e9484d04ac996109846015d0a', '515c9c1e', 'fgarrod', '2025-10-21 17:51:04', 1, '2025-10-21 15:50:49'),
('da966a0a7382dcb456d6c6b408b61182', 'c92f9d1e', 'ngalalv', '2025-12-09 21:08:33', 1, '2025-12-09 20:08:18'),
('dade63cb0ee309fea2101b57787efac3', '6e249d1e', 'aleoveg', '2025-11-25 09:35:19', 0, '2025-11-25 08:35:04'),
('de9339ea215ef9d8b525b931115656d5', '515c9c1e', 'fgarrod', '2025-10-21 22:22:58', 0, '2025-10-21 20:22:43'),
('e9a1f0c40466f9431c592084f9133736', '515c9c1e', 'fgarrod', '2025-10-21 21:40:22', 1, '2025-10-21 19:40:07'),
('eb4cfee9ecb1e53dfd813d6dcf16e887', 'f33e9d1e', NULL, '2025-10-21 22:36:32', 0, '2025-10-21 20:36:17'),
('ec59ee915bea371b3bb03145c5f43298', '515c9c1e', 'fgarrod', '2025-10-21 17:27:38', 0, '2025-10-21 15:27:23'),
('f1153d8fcac3fb1c86e9bd2e6c903bcb', '515c9c1e', 'fgarrod', '2025-10-21 21:21:44', 1, '2025-10-21 19:21:29'),
('f27851e659044b878e5a211113397fab', 'c92f9d1e', 'ngalalv', '2025-10-21 22:24:11', 0, '2025-10-21 20:23:56'),
('f2f0f3c58c57523f6419be41b07af89d', '515c9c1e', 'fgarrod', '2025-10-21 17:14:21', 1, '2025-10-21 15:14:06'),
('f6e132eeae9c144d51345af79da4f20a', 'f33e9d1e', NULL, '2025-10-21 22:36:31', 1, '2025-10-21 20:36:16'),
('fd4db954b84f2469771316e1259f288d', '515c9c1e', 'fgarrod', '2025-10-21 22:22:58', 1, '2025-10-21 20:22:43'),
('fe8822c4281eb6096a92004da0ec8e63', 'f33e9d1e', NULL, '2025-10-21 22:40:24', 1, '2025-10-21 20:40:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `NFC_TOKENS`
--

CREATE TABLE `NFC_TOKENS` (
  `uid` varchar(64) NOT NULL,
  `ldap_uid` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `NFC_TOKENS`
--

INSERT INTO `NFC_TOKENS` (`uid`, `ldap_uid`, `created_at`) VALUES
('515c9c1e', 'fgarrod', '2025-10-20 17:17:30'),
('6E249D1E', 'aleoveg', '2025-10-21 19:56:46'),
('C92F9D1E', 'ngalalv', '2025-10-20 17:17:30'),
('F33E9D1E', 'rgonba4', '2025-10-21 19:57:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `PERSONAS`
--

CREATE TABLE `PERSONAS` (
  `id_personal` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `dni` varchar(15) NOT NULL,
  `empleo_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `PERSONAS`
--

INSERT INTO `PERSONAS` (`id_personal`, `nombre`, `apellidos`, `dni`, `empleo_id`, `activo`) VALUES
(1, 'Juan', 'Pérez López', '12345678A', 1, 1),
(6, 'Juanillo', 'Perez Perez', '34562718R', 1, 1),
(7, 'Nuria', 'Garcia Garcia', '56382713E', 5, 1),
(8, 'Manuel', 'Garcia Fernandez', '49034451J', 2, 1),
(10, 'Jaime', 'Álvarez Teijelo', '59385510Y', 2, 1);

--
-- Disparadores `PERSONAS`
--
DELIMITER $$
CREATE TRIGGER `devolver_stock_al_borrar_persona` BEFORE DELETE ON `PERSONAS` FOR EACH ROW BEGIN
    UPDATE MATERIALES m
    JOIN ENTREGAS_INDIVIDUALES e 
    ON m.id_material = e.id_material
    SET m.stock_total = m.stock_total + 1
    WHERE e.id_personal = OLD.id_personal
    AND e.fecha_devolucion IS NULL;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `USUARIOS`
--

CREATE TABLE `USUARIOS` (
  `id_usuario` int(11) NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `clave_hash` varchar(255) NOT NULL,
  `rol` enum('admin','usuario','armero','furriel','oficina') DEFAULT 'usuario',
  `email` varchar(100) DEFAULT NULL,
  `clave_dovecot` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `USUARIOS`
--

INSERT INTO `USUARIOS` (`id_usuario`, `nombre_usuario`, `clave_hash`, `rol`, `email`, `clave_dovecot`) VALUES
(14, 'ngalalv', '$2y$10$EWv5xWPXfO6kzrQfhdq4.uzFPZL21wGYYMsrH1T6mJbetWzJTivFG', 'usuario', 'ngalalv@intranet.local', '{PLAIN}Patata!1'),
(15, 'fgarrod', '$2y$10$nvlueXzHl0Cn3vYXEo0U3.GnVuzLdD.r0RJuA1AisQFfckITnwxW6', 'furriel', 'fgarrod@intranet.local', '{PLAIN}Patata!5'),
(17, 'aleoveg', '$2y$10$pUk.OaII.uPux2CjblZkQ.ql/Xm46IwSd.CJ/JnEkKFz/Ghodwmqu', 'armero', 'aleoveg@intranet.local', '{PLAIN}Patata!3'),
(20, 'administrador', '$2y$10$20P8LgnMtxUpw9IGEmJ/p.Kcsz0G6bzyR2LQNs3fQA61iQlNjyrfq', 'admin', 'administrador@intranet.local', '{PLAIN}Patata!2'),
(23, 'rgonba4', '$2y$10$XrdKzSMiu560R.wtwJvl7.BnfmWlmBnCA9OfjSqTte8nfHvhRD/NW', 'oficina', 'rgonba4@intranet.local', '{PLAIN}Patata!3');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ARMAMENTO_COLECTIVO`
--
ALTER TABLE `ARMAMENTO_COLECTIVO`
  ADD PRIMARY KEY (`id_armamento`),
  ADD UNIQUE KEY `numero_serie` (`numero_serie`);

--
-- Indices de la tabla `ARMAMENTO_INDIVIDUAL`
--
ALTER TABLE `ARMAMENTO_INDIVIDUAL`
  ADD PRIMARY KEY (`id_arma`),
  ADD UNIQUE KEY `numero_serie` (`numero_serie`);

--
-- Indices de la tabla `ASIGNACION_COLECTIVO`
--
ALTER TABLE `ASIGNACION_COLECTIVO`
  ADD PRIMARY KEY (`id_armamento`,`id_personal`),
  ADD KEY `asignacion_colectivo_ibfk_2` (`id_personal`);

--
-- Indices de la tabla `ASIGNACION_INDIVIDUAL`
--
ALTER TABLE `ASIGNACION_INDIVIDUAL`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD KEY `id_arma` (`id_arma`),
  ADD KEY `asignacion_individual_ibfk_2` (`id_personal`);

--
-- Indices de la tabla `EMPLEOS`
--
ALTER TABLE `EMPLEOS`
  ADD PRIMARY KEY (`id_empleo`);

--
-- Indices de la tabla `ENTREGAS_INDIVIDUALES`
--
ALTER TABLE `ENTREGAS_INDIVIDUALES`
  ADD PRIMARY KEY (`id_entrega`),
  ADD KEY `id_material` (`id_material`),
  ADD KEY `entregas_individuales_ibfk_1` (`id_personal`);

--
-- Indices de la tabla `MATERIALES`
--
ALTER TABLE `MATERIALES`
  ADD PRIMARY KEY (`id_material`);

--
-- Indices de la tabla `NFC_EPHEMERAL`
--
ALTER TABLE `NFC_EPHEMERAL`
  ADD PRIMARY KEY (`token`),
  ADD KEY `FK_EPHEMERAL_UID` (`uid`),
  ADD KEY `FK_EPHEMERAL_USUARIOS` (`ldap_uid`);

--
-- Indices de la tabla `NFC_TOKENS`
--
ALTER TABLE `NFC_TOKENS`
  ADD PRIMARY KEY (`uid`),
  ADD KEY `FK_TOKENS_USUARIOS` (`ldap_uid`);

--
-- Indices de la tabla `PERSONAS`
--
ALTER TABLE `PERSONAS`
  ADD PRIMARY KEY (`id_personal`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `empleo_id` (`empleo_id`);

--
-- Indices de la tabla `USUARIOS`
--
ALTER TABLE `USUARIOS`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ARMAMENTO_COLECTIVO`
--
ALTER TABLE `ARMAMENTO_COLECTIVO`
  MODIFY `id_armamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ARMAMENTO_INDIVIDUAL`
--
ALTER TABLE `ARMAMENTO_INDIVIDUAL`
  MODIFY `id_arma` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `ASIGNACION_INDIVIDUAL`
--
ALTER TABLE `ASIGNACION_INDIVIDUAL`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `EMPLEOS`
--
ALTER TABLE `EMPLEOS`
  MODIFY `id_empleo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `ENTREGAS_INDIVIDUALES`
--
ALTER TABLE `ENTREGAS_INDIVIDUALES`
  MODIFY `id_entrega` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `MATERIALES`
--
ALTER TABLE `MATERIALES`
  MODIFY `id_material` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `PERSONAS`
--
ALTER TABLE `PERSONAS`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `USUARIOS`
--
ALTER TABLE `USUARIOS`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ASIGNACION_COLECTIVO`
--
ALTER TABLE `ASIGNACION_COLECTIVO`
  ADD CONSTRAINT `ASIGNACION_COLECTIVO_ibfk_1` FOREIGN KEY (`id_armamento`) REFERENCES `ARMAMENTO_COLECTIVO` (`id_armamento`),
  ADD CONSTRAINT `asignacion_colectivo_ibfk_2` FOREIGN KEY (`id_personal`) REFERENCES `PERSONAS` (`id_personal`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ASIGNACION_INDIVIDUAL`
--
ALTER TABLE `ASIGNACION_INDIVIDUAL`
  ADD CONSTRAINT `ASIGNACION_INDIVIDUAL_ibfk_1` FOREIGN KEY (`id_arma`) REFERENCES `ARMAMENTO_INDIVIDUAL` (`id_arma`),
  ADD CONSTRAINT `asignacion_individual_ibfk_2` FOREIGN KEY (`id_personal`) REFERENCES `PERSONAS` (`id_personal`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ENTREGAS_INDIVIDUALES`
--
ALTER TABLE `ENTREGAS_INDIVIDUALES`
  ADD CONSTRAINT `ENTREGAS_INDIVIDUALES_ibfk_2` FOREIGN KEY (`id_material`) REFERENCES `MATERIALES` (`id_material`),
  ADD CONSTRAINT `entregas_individuales_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `PERSONAS` (`id_personal`) ON DELETE CASCADE;

--
-- Filtros para la tabla `NFC_EPHEMERAL`
--
ALTER TABLE `NFC_EPHEMERAL`
  ADD CONSTRAINT `FK_EPHEMERAL_UID` FOREIGN KEY (`uid`) REFERENCES `NFC_TOKENS` (`uid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_EPHEMERAL_USUARIOS` FOREIGN KEY (`ldap_uid`) REFERENCES `USUARIOS` (`nombre_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `NFC_TOKENS`
--
ALTER TABLE `NFC_TOKENS`
  ADD CONSTRAINT `FK_TOKENS_USUARIOS` FOREIGN KEY (`ldap_uid`) REFERENCES `USUARIOS` (`nombre_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `PERSONAS`
--
ALTER TABLE `PERSONAS`
  ADD CONSTRAINT `PERSONAS_ibfk_1` FOREIGN KEY (`empleo_id`) REFERENCES `EMPLEOS` (`id_empleo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
