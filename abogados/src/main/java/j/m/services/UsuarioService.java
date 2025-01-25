package j.m.services;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;

import j.m.models.Usuario;
import j.m.utils.DBConnection;

public class UsuarioService {

    
    public Usuario validarUsuario(String username, String password) {
        String query = "SELECT u.id, u.nombre, u.username, u.password, r.nombre AS rol " +
                       "FROM usuarios u " +
                       "JOIN roles r ON u.rol_id = r.id " +
                       "WHERE u.username = ? AND u.password = ?";
    
        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {
    
            stmt.setString(1, username);
            stmt.setString(2, password);
    
            try (ResultSet rs = stmt.executeQuery()) {
                if (rs.next()) {
                    return new Usuario(
                        rs.getInt("id"),
                        rs.getString("nombre"),
                        rs.getString("username"),
                        rs.getString("password"),
                        rs.getString("rol") // Aquí obtenemos el nombre del rol
                    );
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    
        return null; // Usuario no encontrado
    }
}


