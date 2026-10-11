package com.example.operaalerte

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/** Token admin chiffré (demandé une seule fois = "code magique"). */
object TokenStore {
    private const val FILE = "opera_alerte_sec"
    private const val KEY_TOKEN = "opera_token"
    private const val KEY_ROLE = "opera_role"
    private const val KEY_DRIVER = "opera_driver_id"

    private fun prefs(ctx: Context) = EncryptedSharedPreferences.create(
        ctx, FILE,
        MasterKey.Builder(ctx).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
    )

    fun get(ctx: Context): String? =
        try { prefs(ctx).getString(KEY_TOKEN, null) } catch (_: Exception) { null }

    fun save(ctx: Context, token: String) {
        prefs(ctx).edit().putString(KEY_TOKEN, token).apply()
    }

    fun clear(ctx: Context) {
        prefs(ctx).edit().remove(KEY_TOKEN).remove(KEY_ROLE).remove(KEY_DRIVER).apply()
    }

    fun getRole(ctx: Context): String =
        try { prefs(ctx).getString(KEY_ROLE, "") ?: "" } catch (_: Exception) { "" }

    fun saveRole(ctx: Context, role: String) {
        try { prefs(ctx).edit().putString(KEY_ROLE, role).apply() } catch (_: Exception) {}
    }

    fun getDriverId(ctx: Context): Int =
        try { prefs(ctx).getInt(KEY_DRIVER, 0) } catch (_: Exception) { 0 }

    fun saveDriverId(ctx: Context, id: Int) {
        try { prefs(ctx).edit().putInt(KEY_DRIVER, id).apply() } catch (_: Exception) {}
    }

    /** Commandes déjà vues (polling secours anti-doublon d'alarme). */
    fun seenIds(ctx: Context): MutableSet<String> =
        try { prefs(ctx).getStringSet("seen_ids", emptySet())!!.toMutableSet() }
        catch (_: Exception) { mutableSetOf() }

    fun addSeen(ctx: Context, id: String) {
        val s = seenIds(ctx).also { it.add(id) }
        while (s.size > 100) s.remove(s.first())
        prefs(ctx).edit().putStringSet("seen_ids", s).apply()
    }
}
