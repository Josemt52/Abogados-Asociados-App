package j.m;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class DBConnection{
    private static final String url ="jdbc:mysql://localhost:3306/expedientes";
    private static final String user = "root";
    private  static final String pass = "jmtm";

    static {
        try {
            Class.forName("com.mysql.cj.jdbc.Driver");
        } catch (ClassNotFoundException e) {
            throw new RuntimeException("Error al cargar el controlador de MySQL", e);
        }
    }
    
    public static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(url,user,pass);
    }
}