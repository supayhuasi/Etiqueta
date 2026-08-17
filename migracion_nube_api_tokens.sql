-- Tokens de autenticación para la app móvil de "La Nube" (fotos_nube)
CREATE TABLE IF NOT EXISTS nube_api_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  dispositivo VARCHAR(100) DEFAULT NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_uso DATETIME DEFAULT NULL,
  revocado_en DATETIME DEFAULT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
