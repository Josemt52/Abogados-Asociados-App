package j.m.utils;

import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;

public class TextoUtil {
    public static String limpiarHTML(String html) {
        Document doc = Jsoup.parse(html);
        return doc.text().replace("\n", System.lineSeparator()); // Mantiene saltos de línea
    }
}
