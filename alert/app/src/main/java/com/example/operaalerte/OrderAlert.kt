package com.example.operaalerte

import android.content.Intent
import org.json.JSONArray
import org.json.JSONObject

data class OrderItem(val name: String, val qty: Int, val price: Double, val options: String = "")

data class OrderAlert(
    val id: String,
    val total: String,
    val name: String,
    val phone: String,
    val neighborhood: String,
    val items: List<OrderItem>,
    val note: String = "",
    val fish: String = "",
) {
    companion object {
        /** Depuis le détail API GET /api/orders/{id} (articles + options_detail). */
        fun fromOrderJson(o: JSONObject): OrderAlert {
            val items = mutableListOf<OrderItem>()
            val fishNames = mutableListOf<String>()
            val arr = o.optJSONArray("items") ?: JSONArray()
            for (i in 0 until arr.length()) {
                val it = arr.optJSONObject(i) ?: continue
                val det = it.optJSONObject("options_detail")
                val fish = det?.optString("fish", "") ?: ""
                if (fish.isNotEmpty()) fishNames += fish
                val parts = mutableListOf<String>()
                det?.optJSONArray("garnitures")?.let { a ->
                    val g = (0 until a.length()).map { a.optString(it) }.filter { it.isNotEmpty() }
                    if (g.isNotEmpty()) parts += "Garnitures : " + g.joinToString(", ")
                }
                det?.optJSONArray("supplements")?.let { a ->
                    val s = (0 until a.length()).map {
                        val so = a.opt(it)
                        if (so is JSONObject) {
                            val p = so.optDouble("price", 0.0).toLong()
                            so.optString("name") + if (p > 0) " (+$p F)" else ""
                        } else so.toString()
                    }.filter { it.isNotEmpty() }
                    if (s.isNotEmpty()) parts += "Suppléments : " + s.joinToString(", ")
                }
                det?.optJSONArray("parfums")?.let { a ->
                    val p = (0 until a.length()).map { a.optString(it) }.filter { it.isNotEmpty() }
                    if (p.isNotEmpty()) parts += "Parfums : " + p.joinToString(", ")
                }
                if (parts.isEmpty()) {
                    val raw = it.optString("options_text", it.optString("options", ""))
                    if (raw.isNotEmpty()) parts += raw
                }
                items += OrderItem(
                    it.optString("product_name", it.optString("name", "Article")),
                    it.optInt("quantity", 1),
                    it.optDouble("price", 0.0),
                    parts.joinToString(" • "),
                )
            }
            val total = o.optDouble("total_amount", o.optDouble("total", 0.0))
            return OrderAlert(
                id = o.opt("id")?.toString() ?: "?",
                total = "${total.toLong()} FCFA",
                name = o.optString("client_name", "Client"),
                phone = o.optString("client_phone", "-"),
                neighborhood = o.optString("neighborhood", ""),
                items = items,
                note = o.optString("notes", o.optString("note", "")),
                fish = fishNames.distinct().joinToString(", "),
            )
        }

        /** Depuis le payload FCM (data : order_id, total, name, phone, neighborhood, items JSON). */
        fun fromData(d: Map<String, String>): OrderAlert {
            val items = mutableListOf<OrderItem>()
            try {
                val arr = JSONArray(d["items"] ?: "[]")
                for (i in 0 until arr.length()) {
                    val it = arr.optJSONObject(i) ?: continue
                    val sel = it.optJSONArray("selectedOptions")
                    val opts = (0 until (sel?.length() ?: 0)).map {
                        val so = sel!!.opt(it)
                        if (so is JSONObject) so.optString("name", "") else so.toString()
                    }.filter { it.isNotEmpty() }
                    items += OrderItem(
                        it.optString("name", it.optString("product_name", "Article")),
                        it.optInt("quantity", 1),
                        it.optDouble("itemPrice", it.optDouble("price", 0.0)),
                        opts.joinToString(", "),
                    )
                }
            } catch (_: Exception) {}
            return OrderAlert(
                id = d["order_id"] ?: "?",
                total = "${d["total"] ?: "?"} FCFA",
                name = d["name"] ?: "Client",
                phone = d["phone"] ?: "-",
                neighborhood = d["neighborhood"] ?: "",
                items = items,
            )
        }

        fun fromIntent(i: Intent) = OrderAlert(
            id = i.getStringExtra("order_id") ?: "?",
            total = i.getStringExtra("total") ?: "?",
            name = i.getStringExtra("name") ?: "Client",
            phone = i.getStringExtra("phone") ?: "-",
            neighborhood = i.getStringExtra("neighborhood") ?: "",
            items = emptyList(),
            note = i.getStringExtra("note") ?: "",
        )

        fun toIntent(i: Intent, a: OrderAlert): Intent = i
            .putExtra("order_id", a.id)
            .putExtra("total", a.total)
            .putExtra("name", a.name)
            .putExtra("phone", a.phone)
            .putExtra("neighborhood", a.neighborhood)
            .putExtra("note", a.note)
    }
}
