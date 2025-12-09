CREATE DATABASE FURRI_CUARTEL;
USE FURRI_CUARTEL;

-- EMPLEOS
CREATE TABLE EMPLEOS (
    id_empleo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_empleo VARCHAR(50) NOT NULL
);

-- PERSONAL
CREATE TABLE PERSONAS (
    id_personal INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    dni VARCHAR(15) UNIQUE NOT NULL,
    empleo_id INT,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (empleo_id) REFERENCES EMPLEOS(id_empleo)
);

-- MATERIALES INDIVIDUALES
CREATE TABLE MATERIALES (
    id_material INT AUTO_INCREMENT PRIMARY KEY,
    nombre_material VARCHAR(100) NOT NULL,
    descripcion TEXT,
    stock_total INT DEFAULT 0
);

-- ENTREGAS DE MATERIAL INDIVIDUAL
CREATE TABLE ENTREGAS_INDIVIDUALES (
    id_entrega INT AUTO_INCREMENT PRIMARY KEY,
    id_personal INT,
    id_material INT,
    fecha_entrega DATE DEFAULT (CURRENT_DATE),
    fecha_devolucion DATE DEFAULT NULL,
    FOREIGN KEY (id_personal) REFERENCES PERSONAS(id_personal),
    FOREIGN KEY (id_material) REFERENCES MATERIALES(id_material)
);

-- ARMAMENTO INDIVIDUAL (sin columna de persona asignada)
CREATE TABLE ARMAMENTO_INDIVIDUAL (
    id_arma INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    numero_serie VARCHAR(100) UNIQUE,
    estado ENUM('operativo', 'inoperativo', 'escalón') DEFAULT 'operativo',
    tipo ENUM('fusil', 'pistola', 'otro') NOT NULL DEFAULT 'fusil'
);

-- ASIGNACIÓN HISTÓRICA DE ARMAMENTO INDIVIDUAL
CREATE TABLE ASIGNACION_INDIVIDUAL (
    id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
    id_arma INT NOT NULL,
    id_personal INT NOT NULL,
    fecha_asignacion DATE DEFAULT (CURRENT_DATE),
    fecha_devolucion DATE DEFAULT NULL,
    estado ENUM('asignado', 'devuelto', 'extraviado', 'reparación') DEFAULT 'asignado',
    FOREIGN KEY (id_arma) REFERENCES ARMAMENTO_INDIVIDUAL(id_arma),
    FOREIGN KEY (id_personal) REFERENCES PERSONAS(id_personal)
);

-- ARMAMENTO COLECTIVO
CREATE TABLE ARMAMENTO_COLECTIVO (
    id_armamento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    numero_serie VARCHAR(100) UNIQUE,
    estado ENUM('operativo', 'inoperativo', 'escalón') DEFAULT 'operativo',
    asignado_a VARCHAR(100)
);

-- ASIGNACIONES A ARMAMENTO COLECTIVO
CREATE TABLE ASIGNACION_COLECTIVO (
    id_armamento INT,
    id_personal INT,
    fecha_asignacion DATE NOT NULL,
    PRIMARY KEY (id_armamento, id_personal),
    FOREIGN KEY (id_armamento) REFERENCES ARMAMENTO_COLECTIVO(id_armamento),
    FOREIGN KEY (id_personal) REFERENCES PERSONAS(id_personal)
);

CREATE TABLE USUARIOS (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
    clave_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario') DEFAULT 'usuario'
);

-- ========================================
-- DATOS DE EJEMPLO
-- ========================================

-- Empleos
INSERT INTO EMPLEOS (nombre_empleo) VALUES
('Soldado'), ('Cabo'), ('Cabo Primero'), ('Cabo Mayor'), ('Sargento'), ('Sargento Primero'), ('Brigada'),
('Subteniente'), ('Suboficial Mayor'), ('Teniente'), ('Capitán'), ('Comandante'), ('Teniente Coronel'), ('Coronel');

-- Personas
INSERT INTO PERSONAS (nombre, apellidos, dni, empleo_id) VALUES
('Juan', 'Pérez López', '12345678A', 1), ('Carlos', 'Martínez Ruiz', '23456789B', 2), ('Luis', 'García Torres', '34567890C', 3),
('Pedro', 'Sánchez Gómez', '45678901D', 4), ('Miguel', 'Fernández Díaz', '56789012E', 5);

-- Materiales
INSERT INTO MATERIALES (nombre_material, descripcion, stock_total) VALUES
('Mochila de combate', 'Mochila táctica estándar para patrullas', 50),
('Mochila Altus', 'Mochila para llevar todo el material', 50),
('Tienda individual', 'Tienda de campaña individual de lona', 50),
('Casco', 'Casco para el combate', 50),
('Chaleco', 'Chaleco de combate nuevo', 50),
('Zapapico', 'Herramienta individual', 20),
('Esterilla', 'Aislante para el suelo', 50);

-- Entregas de materiales individuales
INSERT INTO ENTREGAS_INDIVIDUALES (id_personal, id_material, fecha_entrega) VALUES
(1, 1, '2025-06-01'), (1, 2, '2025-06-01'), (2, 3, '2025-06-02'), (3, 4, '2025-06-03'), (4, 5, '2025-06-04');

-- Armamento individual (sin asignación directa)
INSERT INTO ARMAMENTO_INDIVIDUAL (nombre, numero_serie, estado, tipo) VALUES
('HK G36E', 'G36E-00123', 'operativo', 'fusil'),
('HK G36E', 'G36E-00124', 'operativo', 'fusil'),
('Pistola HK USP', 'USP-00056', 'operativo', 'pistola'),
('Pistola HK USP', 'USP-00057', 'operativo', 'pistola'),
('HK G36E', 'G36E-00125', 'operativo', 'fusil');

-- Asignaciones individuales con histórico
INSERT INTO ASIGNACION_INDIVIDUAL (id_arma, id_personal, fecha_asignacion) VALUES
(1, 1, '2025-06-01'), (2, 3, '2025-06-02'), (3, 4, '2025-06-03'), (4, 3, '2025-06-04'), (5, 4, '2025-06-05');

-- Armamento colectivo
INSERT INTO ARMAMENTO_COLECTIVO (nombre, numero_serie, estado, asignado_a) VALUES
('Ametralladora MG4', 'MG4-1001', 'operativo', '1ª Sección'),
('Ametralladora MG4', 'MG4-1002', 'operativo', '2ª Sección'),
('Lanzagranadas LAG-40', 'LAG40-2001', 'operativo', '2ª Sección');

-- Asignaciones a armamento colectivo
INSERT INTO ASIGNACION_COLECTIVO (id_armamento, id_personal, fecha_asignacion) VALUES
(1, 1, '2025-06-10'), (1, 2, '2025-06-10'), (2, 3, '2025-06-11');

INSERT INTO USUARIOS (nombre_usuario, clave_hash, rol)
VALUES ('admin', '$2y$10$/B0HIsUIMWMmAP7N2YDh1.qKKsXfcvwDShrtAXgTvdEsE8yB/fxnC', 'admin');
select * from USUARIOS;
DELIMITER $$

CREATE TRIGGER limitar_dos_militares_por_armamento
BEFORE INSERT ON ASIGNACION_COLECTIVO
FOR EACH ROW
BEGIN
    DECLARE total INT;

    SELECT COUNT(*) INTO total
    FROM ASIGNACION_COLECTIVO
    WHERE id_armamento = NEW.id_armamento;

    IF total >= 2 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este armamento ya tiene el máximo de 2 militares asignados.';
    END IF;
END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER limitar_pistolas_por_empleo
BEFORE INSERT ON ASIGNACION_INDIVIDUAL
FOR EACH ROW
BEGIN
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
END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER validar_asignacion_unica_por_tipo
BEFORE INSERT ON ASIGNACION_INDIVIDUAL
FOR EACH ROW
BEGIN
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
END$$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE asignar_material_con_stock (
    IN p_id_personal INT,
    IN p_id_material INT
)
BEGIN
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

DELIMITER $$

CREATE TRIGGER sumar_stock_al_devolver
AFTER UPDATE ON ENTREGAS_INDIVIDUALES
FOR EACH ROW
BEGIN
    -- Solo sumar si antes estaba sin devolver y ahora se ha devuelto
    IF OLD.fecha_devolucion IS NULL AND NEW.fecha_devolucion IS NOT NULL THEN
        UPDATE MATERIALES
        SET stock_total = stock_total + 1
        WHERE id_material = NEW.id_material;
    END IF;
END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER evitar_material_duplicado
BEFORE INSERT ON ENTREGAS_INDIVIDUALES
FOR EACH ROW
BEGIN
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
END$$

DELIMITER ;



ALTER TABLE USUARIOS 
MODIFY rol ENUM('admin', 'usuario', 'armero', 'furriel', 'oficina') DEFAULT 'usuario';

/*INSERT INTO ARMAMENTO_INDIVIDUAL (nombre, numero_serie) VALUES
('HK G36E', 'G36E-0013');
select * from ASIGNACION_INDIVIDUAL;*/
/* DELIMITER $$

CREATE TRIGGER limitar_armas_individuales
BEFORE INSERT ON ARMAMENTO_INDIVIDUAL
FOR EACH ROW
BEGIN
    DECLARE max_armas INT;
    DECLARE total_asignadas INT;

    -- Obtener el empleo del personal
    SELECT empleo_id INTO @empleo_id
    FROM PERSONAS
    WHERE id_personal = NEW.id_personal;

    -- Obtener el máximo permitido para ese empleo
    SELECT max_armas INTO max_armas
    FROM EMPLEOS_ARMAMENTO
    WHERE empleo_id = @empleo_id;

    -- Contar cuántas armas ya tiene asignadas esa persona
    SELECT COUNT(*) INTO total_asignadas
    FROM ARMAMENTO_INDIVIDUAL
    WHERE id_personal = NEW.id_personal;

    -- Validar límite
    IF total_asignadas >= max_armas THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este personal ya tiene el máximo de armas permitido por su empleo.';
    END IF;
END$$

DELIMITER ; */


ALTER TABLE USUARIOS ADD COLUMN email VARCHAR(100) UNIQUE;
ALTER TABLE USUARIOS ADD COLUMN clave_dovecot VARCHAR(255);
select * from USUARIOS;