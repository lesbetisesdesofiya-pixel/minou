package com.example.operaalerte

import android.content.Context

/** Les 3 restaurants : le choix (unique, avant le login) détermine l'URL de l'API. */
object Sites {
    data class Site(val id: String, val label: String, val host: String)

    val ALL = listOf(
        Site("avedji", "Avedji", "avedji.operatogo.net"),
        Site("legbassito", "Legbassito", "legbassito.operatogo.net"),
        Site("avepozo", "Avepozo", "avepozo.operatogo.net"),
    )

    private const val FILE = "opera_alert_prefs"
    private const val KEY = "opera_site"

    private fun prefs(ctx: Context) =
        ctx.getSharedPreferences(FILE, Context.MODE_PRIVATE)

    fun get(ctx: Context): Site? {
        val id = try { prefs(ctx).getString(KEY, null) } catch (_: Exception) { null }
        return ALL.find { it.id == id }
    }

    fun save(ctx: Context, id: String): Site? {
        val s = ALL.find { it.id == id } ?: return null
        try { prefs(ctx).edit().putString(KEY, s.id).apply() } catch (_: Exception) {}
        Api.setSite(s.host)
        return s
    }

    fun clear(ctx: Context) {
        try { prefs(ctx).edit().remove(KEY).apply() } catch (_: Exception) {}
    }

    /** Applique le site stocké à Api (à appeler au démarrage, avant tout appel réseau). */
    fun applyStored(ctx: Context): Site? {
        val s = get(ctx)
        if (s != null) Api.setSite(s.host)
        return s
    }
}
