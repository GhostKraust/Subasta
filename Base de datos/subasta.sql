-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-03-2026 a las 00:07:18
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `subasta`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `rol` varchar(20) NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `admin`
--

INSERT INTO `admin` (`id`, `nombre`, `usuario`, `password`, `fecha_creacion`, `rol`) VALUES
(1, 'Aaron ', 'Ghost', '$2y$10$d1m7DHGoqJ9XUJHV8tUHIe5BMgUw5YWLX9ppN2ohiR.1987KUttOu', '2026-02-24 05:06:37', 'admin'),
(2, '', 'admin', '$2y$10$c0YAPYapWAXTTTaLNxa7PeuwkvP4q.xGq9Bs872mCL4PlptFg3cKe', '2026-02-25 01:32:13', 'admin'),
(4, '', 'Cris', '$2y$10$JA2Bq5g9nSsjRyt0/4ZDte4FlNo1VK0L/liietMIIqJKOfR5ZXbva', '2026-02-28 21:01:13', 'operativo'),
(5, '', 'Miriam', '$2y$10$OkIrYjqSuCo5RS8Xv.YZ5OyuEDhISYnHrwpWZpLgFwTG0eOJ5RYTe', '2026-03-02 19:53:09', 'operativo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`) VALUES
(1, 'Hotelería '),
(2, 'Otros '),
(3, 'Joyería '),
(4, 'Restaurantes ');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `moneda` char(3) NOT NULL,
  `tasa` decimal(12,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `exchange_rates`
--

INSERT INTO `exchange_rates` (`moneda`, `tasa`) VALUES
('CAD', 13.5000),
('USD', 17.0000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ganadores_historial`
--

CREATE TABLE `ganadores_historial` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `producto_nombre` varchar(255) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `nombre_usuario` varchar(150) DEFAULT NULL,
  `correo_usuario` varchar(150) DEFAULT NULL,
  `telefono_usuario` varchar(20) DEFAULT NULL,
  `monto_puja` decimal(10,2) DEFAULT NULL,
  `fecha_puja` datetime DEFAULT NULL,
  `fecha_cierre` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ganadores_historial`
--

INSERT INTO `ganadores_historial` (`id`, `producto_id`, `producto_nombre`, `categoria_id`, `nombre_usuario`, `correo_usuario`, `telefono_usuario`, `monto_puja`, `fecha_puja`, `fecha_cierre`, `created_at`) VALUES
(1, 1, 'HOTEL W PUNTA MITA', 1, 'El rubio', 'Elrubio2508004@gmail.com', '1234567891', 6000.00, '2026-02-24 18:45:47', '2026-02-24 12:47:00', '2026-03-05 18:27:16'),
(2, 2, 'Porche', 2, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 1300000.00, '2026-02-25 10:26:37', '2026-02-27 13:10:00', '2026-03-05 18:27:16'),
(3, 5, 'Eclipse Towers', 1, 'Miriam', 'info@pasitosdeluz.org', '3221379020', 10000.00, '2026-03-02 14:09:18', '2026-03-03 16:38:00', '2026-03-05 18:27:16'),
(4, 6, 'Tequila sinsimitro', 2, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 700000.00, '2026-03-03 12:15:27', '2026-03-03 22:45:00', '2026-03-05 18:27:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ganadores_notificados`
--

CREATE TABLE `ganadores_notificados` (
  `producto_id` int(11) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `enviado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_productos`
--

CREATE TABLE `historial_productos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `producto_nombre` varchar(255) DEFAULT NULL,
  `accion` varchar(30) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL,
  `cambios` longtext DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_productos`
--

INSERT INTO `historial_productos` (`id`, `producto_id`, `producto_nombre`, `accion`, `usuario_id`, `usuario_nombre`, `cambios`, `ip`, `created_at`) VALUES
(1, 6, 'Tequila sinsimitro', 'reactivar', 1, 'Ghost', '{\"changes\":{\"estado\":{\"before\":\"finalizado\",\"after\":\"activo\"},\"fecha_inicio\":{\"before\":\"2026-03-07 14:01:00\",\"after\":\"2026-03-06 12:35:00\"},\"fecha_fin\":{\"before\":\"2026-03-15 14:01:00\",\"after\":\"2026-03-09 13:35:00\"}}}', '::1', '2026-03-06 18:35:30'),
(2, 6, 'Tequila sinsimitro', 'retirar', 1, 'Ghost', '{\"changes\":{\"estado\":{\"before\":\"activo\",\"after\":\"finalizado\"}}}', '::1', '2026-03-06 18:38:57'),
(3, 6, 'Tequila sinsimitro', 'reactivar', 1, 'Ghost', '{\"changes\":{\"estado\":{\"before\":\"finalizado\",\"after\":\"activo\"},\"fecha_inicio\":{\"before\":\"2026-03-06 12:35:00\",\"after\":\"2026-03-06 14:40:00\"},\"fecha_fin\":{\"before\":\"2026-03-09 13:35:00\",\"after\":\"2026-03-08 15:40:00\"}}}', '::1', '2026-03-06 20:40:28'),
(4, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-06 14:40:00\",\"after\":\"2026-03-06 14:40\"},\"fecha_fin\":{\"before\":\"2026-03-08 15:40:00\",\"after\":\"2026-03-13 15:40\"},\"estado\":{\"before\":\"\",\"after\":\"activo\"}}}', '::1', '2026-03-09 17:04:10'),
(5, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-06 14:40:00\",\"after\":\"2026-03-06 14:40\"},\"fecha_fin\":{\"before\":\"2026-03-13 15:40:00\",\"after\":\"2026-03-13 15:40\"}}}', '::1', '2026-03-09 19:49:18'),
(6, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"imagen_url\":{\"before\":\"uploads\\/productos\\/producto_20260224_123331_3eb78418.png\",\"after\":\"[\\\"uploads\\\\\\/productos\\\\\\/producto_20260310_132930_0b8f0098_0.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260310_132930_29854831_1.jpg\\\"]\"},\"fecha_inicio\":{\"before\":\"2026-03-06 14:40:00\",\"after\":\"2026-03-06 14:40\"},\"fecha_fin\":{\"before\":\"2026-03-13 15:40:00\",\"after\":\"2026-03-13 15:40\"}}}', '::1', '2026-03-10 19:29:30'),
(7, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-06 14:40:00\",\"after\":\"2026-03-12 12:00\"},\"fecha_fin\":{\"before\":\"2026-03-13 15:40:00\",\"after\":\"2026-03-13 15:40\"},\"fecha_expiracion\":{\"before\":null,\"after\":\"2027-04-12 00:18\"}}}', '::1', '2026-03-12 16:18:37'),
(8, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-12 12:00:00\",\"after\":\"2026-03-14 13:00\"},\"fecha_fin\":{\"before\":\"2026-03-13 15:40:00\",\"after\":\"2026-03-31 13:00\"},\"fecha_expiracion\":{\"before\":\"2027-04-12 00:18:00\",\"after\":\"2027-04-13 12:18\"}}}', '::1', '2026-03-12 16:22:13'),
(9, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-14 13:00:00\",\"after\":\"2026-03-14 10:24\"},\"fecha_fin\":{\"before\":\"2026-03-31 13:00:00\",\"after\":\"2026-03-31 13:00\"},\"fecha_expiracion\":{\"before\":\"2027-04-13 12:18:00\",\"after\":\"2027-04-13 12:18\"}}}', '::1', '2026-03-12 16:22:58'),
(10, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-14 10:24:00\",\"after\":\"2026-03-12 10:25\"},\"fecha_fin\":{\"before\":\"2026-03-31 13:00:00\",\"after\":\"2026-03-31 13:00\"},\"fecha_expiracion\":{\"before\":\"2027-04-13 12:18:00\",\"after\":\"2027-04-13 12:18\"}}}', '::1', '2026-03-12 16:24:38'),
(11, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-12 10:25:00\",\"after\":\"2026-03-12 10:30\"},\"fecha_fin\":{\"before\":\"2026-03-31 13:00:00\",\"after\":\"2026-03-31 13:00\"},\"fecha_expiracion\":{\"before\":\"2027-04-13 12:18:00\",\"after\":\"2027-04-13 12:18\"}}}', '::1', '2026-03-12 16:25:39'),
(12, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-12 10:30:00\",\"after\":\"2026-03-12 10:28\"},\"fecha_fin\":{\"before\":\"2026-03-31 13:00:00\",\"after\":\"2026-03-31 13:00\"},\"fecha_expiracion\":{\"before\":\"2027-04-13 12:18:00\",\"after\":\"2027-04-13 12:18\"}}}', '::1', '2026-03-12 16:26:58'),
(13, 8, 'Porche 911 Coupé', 'crear', 1, 'Ghost', '{\"before\":null,\"after\":{\"nombre\":\"Porche 911 Coupé\",\"descripcion\":\"Porche de carreras con aceleraccion de 0 a 100 en 4.1 segundos, con velocidad de 294KKm\\/h, motor de 6 cilindros con motor boxer de 3.0 litros\",\"imagen_url\":\"[\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_04daa030_0.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_d0fe3b83_1.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_6c19bb11_2.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_6a0fc78c_3.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_c4e9ca3b_4.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_94fb87d8_5.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_d3cf08ba_6.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_a66b6552_7.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_3757e9af_8.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_3937ad89_9.jpg\\\"]\",\"precio_inicial\":1000000,\"incremento_minimo\":1000000,\"fecha_inicio\":\"2026-03-12 12:00\",\"fecha_fin\":\"2026-03-31 00:00\",\"fecha_expiracion\":\"2027-12-29 10:44\",\"categoria_id\":2,\"estado\":\"activo\"}}', '::1', '2026-03-12 16:45:36'),
(14, 8, 'Porche 911 Coupé', 'editar', 1, 'Ghost', '{\"changes\":{\"imagen_url\":{\"before\":\"[\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_04daa030_0.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_d0fe3b83_1.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_6c19bb11_2.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_104536_6a0fc78c_3.jpg\\\",\\\"uploa\",\"after\":\"[\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_9c8034a4_0.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_858e79b9_1.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_642b6888_2.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_7716f797_3.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_269c6946_4.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_08674392_5.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_db44db78_6.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_f75bc027_7.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_84982135_8.jpg\\\",\\\"uploads\\\\\\/productos\\\\\\/producto_20260312_110756_67592d3c_9.jpg\\\"]\"},\"fecha_inicio\":{\"before\":\"2026-03-12 12:00:00\",\"after\":\"2026-03-12 12:00\"},\"fecha_fin\":{\"before\":\"2026-03-31 00:00:00\",\"after\":\"2026-03-31 00:00\"},\"fecha_expiracion\":{\"before\":\"2027-12-29 10:44:00\",\"after\":\"2027-12-29 10:44\"}}}', '::1', '2026-03-12 17:07:56'),
(15, 6, 'Tequila sinsimitro', 'editar', 1, 'Ghost', '{\"changes\":{\"fecha_inicio\":{\"before\":\"2026-03-12 10:28:00\",\"after\":\"2026-03-12 10:28\"},\"fecha_fin\":{\"before\":\"2026-03-31 13:00:00\",\"after\":\"2026-03-18 13:00\"},\"fecha_expiracion\":{\"before\":\"2027-04-13 12:18:00\",\"after\":\"2027-04-13 12:18\"}}}', '::1', '2026-03-12 23:56:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_intentos`
--

CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `intentos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `login_intentos`
--

INSERT INTO `login_intentos` (`id`, `usuario`, `ip`, `intentos`, `bloqueado_hasta`, `actualizado_en`) VALUES
(5, 'Arturo', '::1', 2, NULL, '2026-03-12 09:49:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_url` text DEFAULT NULL,
  `precio_inicial` decimal(10,2) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `estado` enum('activo','finalizado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `incremento_minimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_expiracion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `imagen_url`, `precio_inicial`, `categoria_id`, `estado`, `fecha_creacion`, `fecha_inicio`, `fecha_fin`, `incremento_minimo`, `fecha_expiracion`) VALUES
(1, 'HOTEL W PUNTA MITA', 'Quedate en el hotel por 2 noches y 3 dias', 'uploads/productos/producto_20260224_005444_9908e7f1.jpg', 1500.00, 1, 'finalizado', '2026-02-24 05:54:44', '2026-02-24 12:44:00', '2026-02-24 12:47:00', 500.00, NULL),
(3, 'Saludo marciano', 'Pintura de arte de del pintor el \"El rubio\"', 'uploads/productos/producto_20260224_121752_81ee0e07.png', 157500.00, 2, 'finalizado', '2026-02-25 00:17:52', '2026-03-04 16:20:00', '2026-03-04 20:22:00', 100000.00, NULL),
(4, 'Rubi Rosa', 'Joya muy valiosa desde las islas cayo perico', 'uploads/productos/producto_20260224_122735_a3d964b4.png', 1200000.00, 3, 'finalizado', '2026-02-25 00:27:35', '2026-03-05 15:41:00', '2026-03-07 15:40:00', 500000.00, NULL),
(5, 'Eclipse Towers', 'Quedate 2 noches y 3 dias en el Eclipse Towers en los santos vinewood', 'uploads/productos/producto_20260224_122946_9e48534a.png', 2000.00, 1, 'finalizado', '2026-02-25 00:29:47', '2026-03-01 14:41:00', '2026-03-03 16:38:00', 1000.00, NULL),
(6, 'Tequila sinsimitro', 'Tequila por mas de 5 años de añejamiento distribuido por \"El rubio\"', '[\"uploads\\/productos\\/producto_20260310_132930_0b8f0098_0.jpg\",\"uploads\\/productos\\/producto_20260310_132930_29854831_1.jpg\"]', 600000.00, 2, 'activo', '2026-02-25 00:33:31', '2026-03-12 10:28:00', '2026-03-18 13:00:00', 100000.00, '2027-04-13 12:18:00'),
(8, 'Porche 911 Coupé', 'Porche de carreras con aceleraccion de 0 a 100 en 4.1 segundos, con velocidad de 294KKm/h, motor de 6 cilindros con motor boxer de 3.0 litros', '[\"uploads\\/productos\\/producto_20260312_110756_9c8034a4_0.jpg\",\"uploads\\/productos\\/producto_20260312_110756_858e79b9_1.jpg\",\"uploads\\/productos\\/producto_20260312_110756_642b6888_2.jpg\",\"uploads\\/productos\\/producto_20260312_110756_7716f797_3.jpg\",\"uploads\\/productos\\/producto_20260312_110756_269c6946_4.jpg\",\"uploads\\/productos\\/producto_20260312_110756_08674392_5.jpg\",\"uploads\\/productos\\/producto_20260312_110756_db44db78_6.jpg\",\"uploads\\/productos\\/producto_20260312_110756_f75bc027_7.jpg\",\"uploads\\/productos\\/producto_20260312_110756_84982135_8.jpg\",\"uploads\\/productos\\/producto_20260312_110756_67592d3c_9.jpg\"]', 1000000.00, 2, 'activo', '2026-03-12 16:45:36', '2026-03-12 12:00:00', '2026-03-31 00:00:00', 1000000.00, '2027-12-29 10:44:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pujas`
--

CREATE TABLE `pujas` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `nombre_usuario` varchar(150) NOT NULL,
  `correo_usuario` varchar(150) NOT NULL,
  `telefono_usuario` varchar(20) NOT NULL,
  `monto_puja` decimal(10,2) NOT NULL,
  `fecha_puja` timestamp NOT NULL DEFAULT current_timestamp(),
  `origen` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pujas`
--

INSERT INTO `pujas` (`id`, `producto_id`, `nombre_usuario`, `correo_usuario`, `telefono_usuario`, `monto_puja`, `fecha_puja`, `origen`) VALUES
(1, 1, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567890', 5000.00, '2026-02-25 00:45:00', NULL),
(2, 1, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567890', 5500.00, '2026-02-25 00:45:22', NULL),
(3, 1, 'El rubio', 'Elrubio2508004@gmail.com', '1234567891', 6000.00, '2026-02-25 00:45:47', NULL),
(12, 5, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 3000.00, '2026-02-28 20:39:56', NULL),
(13, 5, 'El rubio', 'Aaron2508004@gmail.com', '1234567892', 4000.00, '2026-02-28 20:44:06', NULL),
(14, 5, 'Miriam', 'info@pasitosdeluz.org', '3221379020', 10000.00, '2026-03-02 20:09:18', NULL),
(18, 6, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 700000.00, '2026-03-09 18:03:31', 'anonimo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD PRIMARY KEY (`moneda`);

--
-- Indices de la tabla `ganadores_historial`
--
ALTER TABLE `ganadores_historial`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_producto_cierre` (`producto_id`,`fecha_cierre`),
  ADD KEY `idx_fecha_cierre` (`fecha_cierre`);

--
-- Indices de la tabla `ganadores_notificados`
--
ALTER TABLE `ganadores_notificados`
  ADD PRIMARY KEY (`producto_id`);

--
-- Indices de la tabla `historial_productos`
--
ALTER TABLE `historial_productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_producto` (`producto_id`),
  ADD KEY `idx_accion` (`accion`),
  ADD KEY `idx_fecha` (`created_at`);

--
-- Indices de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_usuario_ip` (`usuario`,`ip`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Indices de la tabla `pujas`
--
ALTER TABLE `pujas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `ganadores_historial`
--
ALTER TABLE `ganadores_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `historial_productos`
--
ALTER TABLE `historial_productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `pujas`
--
ALTER TABLE `pujas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);

--
-- Filtros para la tabla `pujas`
--
ALTER TABLE `pujas`
  ADD CONSTRAINT `pujas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
