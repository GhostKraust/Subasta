-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-03-2026 a las 17:04:03
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
(1, 'Aaron ', 'Ghost', '123456', '2026-02-24 05:06:37', 'admin'),
(2, '', 'admin', '$2y$10$c0YAPYapWAXTTTaLNxa7PeuwkvP4q.xGq9Bs872mCL4PlptFg3cKe', '2026-02-25 01:32:13', 'admin'),
(4, '', 'Cris', '$2y$10$JA2Bq5g9nSsjRyt0/4ZDte4FlNo1VK0L/liietMIIqJKOfR5ZXbva', '2026-02-28 21:01:13', 'operativo'),
(5, '', 'Miriam', '$2y$10$0bUhF9EKbg9jvrFK0K.jauSDlSy7t0lT0o7E0.tmQBu/nzdrPpvGy', '2026-03-02 19:53:09', 'admin');

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
-- Estructura de tabla para la tabla `ganadores_notificados`
--

CREATE TABLE `ganadores_notificados` (
  `producto_id` int(11) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `enviado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `precio_inicial` decimal(10,2) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `estado` enum('activo','finalizado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `incremento_minimo` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `imagen_url`, `precio_inicial`, `categoria_id`, `estado`, `fecha_creacion`, `fecha_inicio`, `fecha_fin`, `incremento_minimo`) VALUES
(1, 'HOTEL W PUNTA MITA', 'Quedate en el hotel por 2 noches y 3 dias', 'uploads/productos/producto_20260224_005444_9908e7f1.jpg', 1500.00, 1, 'finalizado', '2026-02-24 05:54:44', '2026-02-24 12:44:00', '2026-02-24 12:47:00', 500.00),
(2, 'Porche', 'Maneja un elegante porche en las calles de tu ciudad', 'uploads/productos/producto_20260224_062507_ebbe5215.jpg', 500000.00, 2, 'finalizado', '2026-02-24 11:25:07', '2026-02-24 13:48:00', '2026-02-27 13:10:00', 100000.00),
(3, 'Saludo marciano', 'Pintura de arte de del pintor el \"El rubio\"', 'uploads/productos/producto_20260224_121752_81ee0e07.png', 157500.00, 2, 'activo', '2026-02-25 00:17:52', '2026-03-04 16:20:00', '2026-03-04 20:22:00', 100000.00),
(4, 'Rubi Rosa', 'Joya muy valiosa desde las islas cayo perico', 'uploads/productos/producto_20260224_122735_a3d964b4.png', 1200000.00, 3, 'activo', '2026-02-25 00:27:35', '2026-03-02 18:30:00', '2026-03-03 20:30:00', 500000.00),
(5, 'Eclipse Towers', 'Quedate 2 noches y 3 dias en el Eclipse Towers en los santos vinewood', 'uploads/productos/producto_20260224_122946_9e48534a.png', 2000.00, 1, 'activo', '2026-02-25 00:29:47', '2026-03-01 14:41:00', '2026-03-03 16:38:00', 1000.00),
(6, 'Tequila sinsimitro', 'Tequila por mas de 5 años de añejamiento distribuido por \"El rubio\"', 'uploads/productos/producto_20260224_123331_3eb78418.png', 600000.00, 2, 'activo', '2026-02-25 00:33:31', '2025-12-25 18:36:00', '2026-03-03 22:45:00', 100000.00),
(7, 'abbraccio', 'Prueba pasitos', 'uploads/productos/producto_20260303_122400_ce989439.jpg', 100.00, 4, 'activo', '2026-03-03 18:24:00', '2026-03-02 12:23:00', '2026-03-05 12:23:00', 50.00);

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
  `fecha_puja` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pujas`
--

INSERT INTO `pujas` (`id`, `producto_id`, `nombre_usuario`, `correo_usuario`, `telefono_usuario`, `monto_puja`, `fecha_puja`) VALUES
(1, 1, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567890', 5000.00, '2026-02-25 00:45:00'),
(2, 1, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567890', 5500.00, '2026-02-25 00:45:22'),
(3, 1, 'El rubio', 'Elrubio2508004@gmail.com', '1234567891', 6000.00, '2026-02-25 00:45:47'),
(4, 2, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567890', 600000.00, '2026-02-25 01:48:20'),
(5, 2, 'Presencial', 'presencial@local', '0000000000', 700000.00, '2026-02-25 01:48:39'),
(6, 2, 'El rubio', 'Elrubio2508004@gmail.com', '1234567891', 800000.00, '2026-02-25 01:50:28'),
(7, 2, 'Simeon', 'Simeon2508004@gmail.com', '1234567892', 900000.00, '2026-02-25 02:36:05'),
(8, 2, 'Clary', 'Clary2508004@gmail.com', '1234567892', 1000000.00, '2026-02-25 15:48:10'),
(9, 2, 'Presencial', 'presencial@local', '0000000000', 1100000.00, '2026-02-25 15:53:05'),
(10, 2, 'Presencial', 'presencial@local', '0000000000', 1200000.00, '2026-02-25 16:23:17'),
(11, 2, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 1300000.00, '2026-02-25 16:26:37'),
(12, 5, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 3000.00, '2026-02-28 20:39:56'),
(13, 5, 'El rubio', 'Aaron2508004@gmail.com', '1234567892', 4000.00, '2026-02-28 20:44:06'),
(14, 5, 'Miriam', 'info@pasitosdeluz.org', '3221379020', 10000.00, '2026-03-02 20:09:18'),
(15, 6, 'Aaron Monroy', 'Aaron2508004@gmail.com', '1234567892', 700000.00, '2026-03-03 18:15:27');

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
-- Indices de la tabla `ganadores_notificados`
--
ALTER TABLE `ganadores_notificados`
  ADD PRIMARY KEY (`producto_id`);

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
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `pujas`
--
ALTER TABLE `pujas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
