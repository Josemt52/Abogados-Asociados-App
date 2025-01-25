package j.m.utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class DBConnection {
    private static final String url = "jdbc:mysql://127.0.0.1:3306/expediente";
    private static final String user = "root";
    private static final String pass = "jmtm";
    private static Connection connection = null;

    private DBConnection() {
        // Constructor privado para evitar instanciación
    }

    public static Connection getConnection() {
        try {
            Class.forName("com.mysql.cj.jdbc.Driver");
            return DriverManager.getConnection(url, user, pass);
        } catch (ClassNotFoundException | SQLException e) {
            System.err.println("Error al establecer la conexión con la base de datos: " + e.getMessage());
            return null;
        }
    }
    

    public static void closeConnection() {
        if (connection != null) {
            try {
                connection.close();
            } catch (SQLException e) {
                System.err.println("Error al cerrar la conexión con la base de datos: " + e.getMessage());
            }
        }
    }
}
