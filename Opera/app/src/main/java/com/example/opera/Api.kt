package com.example.opera

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/** Client minimal de l'API Opera (aucune dépendance : HttpURLConnection + org.json). */
object Api {
    const val BASE = "https://avepozo.operatogo.net/api"
    const val WEB = "https://avepozo.operatogo.net"

    fun dashUrl(token: String) = "$WEB/app/#/admin/commandes?token=$token"
    fun detailUrl(orderId: String, token: String) = "$WEB/app/#/admin/commandes/$orderId?token=$token"

    private fun req(
        method: String,
        path: String,
        token: String? = null,
        body: JSONObject? = null,
    ): Pair<Int, String> {
        val conn = (URL(BASE + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15000
            readTimeout = 15000
            setRequestProperty("Accept", "application/json")
            if (token != null) setRequestProperty("Authorization", "Bearer $token")
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
                outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            }
        }
        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val text = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""
        conn.disconnect()
        return code to text
    }

    /** POST /api/auth/login -> (token, role). */
    suspend fun login(email: String, password: String): Pair<String, String> =
        withContext(Dispatchers.IO) {
            val (code, text) = req("POST", "/auth/login",
                body = JSONObject().put("email", email).put("password", password))
            if (code != 200) throw Exception(JSONObject(text).optString("error", "Identifiants invalides."))
            val j = JSONObject(text)
            j.getString("token") to j.getString("role")
        }

    /** GET /api/orders/{id} (detail + articles + livreur). */
    suspend fun getOrder(token: String, id: String): JSONObject =
        withContext(Dispatchers.IO) {
            val (code, text) = req("GET", "/orders/$id", token)
            if (code != 200) throw Exception("Commande introuvable.")
            JSONObject(text)
        }

    /** GET /api/orders?limit=N (polling secours). */
    suspend fun listOrders(token: String, limit: Int = 10): JSONArray =
        withContext(Dispatchers.IO) {
            val (code, text) = req("GET", "/orders?limit=$limit", token)
            if (code != 200) throw Exception("Chargement impossible.")
            JSONArray(text)
        }

    /** PATCH /api/orders/{id}/status. Annulée refusée par l'API -> repli 'pending' (comme le React). */
    suspend fun setStatus(token: String, id: String, status: String) {
        withContext(Dispatchers.IO) {
            val (code, _) = req("PATCH", "/orders/$id/status", token,
                JSONObject().put("status", status))
            if (code != 200) {
                if (status == "Annulée") {
                    req("PATCH", "/orders/$id/status", token, JSONObject().put("status", "pending"))
                } else throw Exception("Statut refusé.")
            }
        }
    }
}
