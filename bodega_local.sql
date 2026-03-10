-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 10-03-2026 a las 14:36:39
-- Versión del servidor: 8.0.30
-- Versión de PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bodega_local`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_salida`
--

CREATE TABLE `historial_salida` (
  `id_historial_salida` int NOT NULL,
  `id_producto` int DEFAULT NULL,
  `nombre_producto` varchar(255) NOT NULL,
  `modulo` enum('Caja','Paca','Paquete','Pedazo','Unidad') NOT NULL,
  `cantidad_grande` int NOT NULL,
  `cantidad_unidad` int NOT NULL,
  `documento_ref` varchar(100) DEFAULT NULL,
  `fecha_salida` datetime DEFAULT CURRENT_TIMESTAMP,
  `destino` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `historial_salida`
--

INSERT INTO `historial_salida` (`id_historial_salida`, `id_producto`, `nombre_producto`, `modulo`, `cantidad_grande`, `cantidad_unidad`, `documento_ref`, `fecha_salida`, `destino`) VALUES
(5, 1, 'cocacola 1.5', 'Caja', 1, 0, 'firmado', '2026-03-09 13:37:44', 'lirios'),
(6, 3, 'arroz diana', 'Paca', 2, 0, 'firmado', '2026-03-10 07:38:53', 'lirios');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_venta`
--

CREATE TABLE `historial_venta` (
  `id_historial_venta` int NOT NULL,
  `id_producto` int DEFAULT NULL,
  `nombre_producto` varchar(255) NOT NULL,
  `modulo` enum('Caja','Paca','Paquete','Pedazo','Unidad') NOT NULL,
  `cantidad_grande` int NOT NULL,
  `cantidad_unidad` int NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `fecha_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  `metodo_pago` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `historial_venta`
--

INSERT INTO `historial_venta` (`id_historial_venta`, `id_producto`, `nombre_producto`, `modulo`, `cantidad_grande`, `cantidad_unidad`, `precio`, `fecha_hora`, `metodo_pago`) VALUES
(47, 2, 'arroz pinillar', 'Paca', 0, 2, 8000.00, '2026-03-10 09:13:34', 'nequi'),
(48, 2, 'arroz pinillar', 'Paca', 0, 1, 4000.00, '2026-03-10 09:13:54', 'nequi'),
(49, 2, 'arroz pinillar', 'Paca', 0, 4, 16000.00, '2026-03-10 09:14:14', 'efectivo'),
(50, 2, 'arroz pinillar', 'Paca', 0, 4, 16000.00, '2026-03-10 09:14:41', 'efectivo'),
(51, 2, 'arroz pinillar', 'Paca', 0, 1, 4000.00, '2026-03-10 09:15:21', 'efectivo'),
(52, 2, 'arroz pinillar', 'Paca', 0, 2, 8000.00, '2026-03-10 09:15:57', 'nequi'),
(53, 1, 'cocacola 1.5', 'Caja', 1, 0, 50000.00, '2026-03-10 09:33:10', 'efectivo'),
(54, 3, 'arroz diana', 'Paca', 1, 0, 80000.00, '2026-03-10 09:34:30', 'nequi');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int NOT NULL,
  `id_proveedor` int DEFAULT NULL,
  `nombre_producto` varchar(255) NOT NULL,
  `descripcion` text,
  `marca` varchar(100) DEFAULT NULL,
  `categoria` enum('Bebidas','Alimentos','Limpieza','Higiene','Otros') NOT NULL,
  `presentacion` varchar(100) DEFAULT NULL,
  `modulo` enum('Caja','Paca','Paquete','Pedazo','Unidad') NOT NULL,
  `cantidad_grande` int DEFAULT '0' COMMENT 'Cantidad de paquetes/pacas/cajas grandes',
  `cantidad_unidad` int DEFAULT '0' COMMENT 'Unidades por cada cantidad grande (ej: 45 unidades por paca)',
  `unidades_sueltas` int DEFAULT '0' COMMENT 'Unidades adicionales fuera de la cantidad grande',
  `precio_compra` decimal(10,2) NOT NULL,
  `precio_venta_unidad` decimal(10,2) NOT NULL,
  `precio_venta_cantidad_grande` decimal(10,2) NOT NULL,
  `fecha_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `id_proveedor`, `nombre_producto`, `descripcion`, `marca`, `categoria`, `presentacion`, `modulo`, `cantidad_grande`, `cantidad_unidad`, `unidades_sueltas`, `precio_compra`, `precio_venta_unidad`, `precio_venta_cantidad_grande`, `fecha_hora`, `observaciones`) VALUES
(1, 2, 'cocacola 1.5', 'bebida gaseosa', 'coca-cola', 'Bebidas', 'botella 1.5l', 'Caja', 5, 12, 1, 40000.00, 7000.00, 50000.00, '2026-03-09 12:35:13', ''),
(2, 2, 'arroz pinillar', 'arroz pinillar de calidad', 'pinillar', 'Alimentos', 'pacax24', 'Paca', 1, 24, 2, 82000.00, 4000.00, 84000.00, '2026-03-09 12:39:01', 'ninguna'),
(3, 4, 'arroz diana', 'alimentos para cocina', 'diana', 'Alimentos', 'bolsa 1k', 'Paca', 5, 24, 6, 75000.00, 3500.00, 80000.00, '2026-03-10 07:37:11', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int NOT NULL,
  `nombre_proveedor` varchar(255) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` text,
  `email` varchar(100) DEFAULT NULL,
  `contacto2` varchar(100) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `nombre_proveedor`, `telefono`, `direccion`, `email`, `contacto2`, `fecha_registro`) VALUES
(2, 'madre tierraa', '3215754216', 'carr 17 calle13', 'madre_tierra@gmail.com', '', '2026-03-09 12:32:51'),
(3, 'marta', '54654645654645', 'carr 17 calle13', 'piwisher1423@gmail.com', '5446546', '2026-03-09 12:42:04'),
(4, 'paco', '5465464', 'carr 17 calle16', 'madra@gmail.com', '5446546', '2026-03-10 07:34:51');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `historial_salida`
--
ALTER TABLE `historial_salida`
  ADD PRIMARY KEY (`id_historial_salida`),
  ADD KEY `idx_historial_salida_producto` (`id_producto`);

--
-- Indices de la tabla `historial_venta`
--
ALTER TABLE `historial_venta`
  ADD PRIMARY KEY (`id_historial_venta`),
  ADD KEY `idx_historial_venta_producto` (`id_producto`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `idx_productos_proveedor` (`id_proveedor`),
  ADD KEY `idx_productos_categoria` (`categoria`),
  ADD KEY `idx_productos_modulo` (`modulo`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `historial_salida`
--
ALTER TABLE `historial_salida`
  MODIFY `id_historial_salida` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `historial_venta`
--
ALTER TABLE `historial_venta`
  MODIFY `id_historial_venta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `historial_salida`
--
ALTER TABLE `historial_salida`
  ADD CONSTRAINT `historial_salida_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE SET NULL;

--
-- Filtros para la tabla `historial_venta`
--
ALTER TABLE `historial_venta`
  ADD CONSTRAINT `historial_venta_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE SET NULL;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
