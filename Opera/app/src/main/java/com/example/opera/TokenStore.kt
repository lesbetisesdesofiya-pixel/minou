package com.example.opera

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/** Token admin chiffré (= le "code magique" : demandé une seule fois). */
object TokenStore {
    private const val FILE = "opera_sec"
    private const val KEY_TOKEN = "opera_token"

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
        prefs(ctx).edit().remove(KEY_TOKEN).apply()
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
