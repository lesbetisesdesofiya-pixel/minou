package com.example.opera

import android.content.Intent
import org.json.JSONArray
import org.json.JSONObject

data class OrderItem(val name: String, val qty: Int, val price: Double)
data class OrderAlert(
    val id: String,
    val total: String,
    val name: String,
    val phone: String,
    val neighborhood: String,
    val items: List<OrderItem>,
    val note: String = "",
) {
    companion object {
        /** Depuis le détail API GET /api/orders/{id} (polling secours). */
        fun fromOrderJson(o: JSONObject): OrderAlert {
            val items = mutableListOf<OrderItem>()
            val arr = o.optJSONArray("items") ?: JSONArray()
            for (i in 0 until arr.length()) {
                val it = arr.optJSONObject(i) ?: continue
                items += OrderItem(
                    it.optString("product_name", it.optString("name", "Article")),
                    it.optInt("quantity", 1),
                    it.optDouble("price", 0.0),
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
            )
        }

        /** Depuis le payload FCM (data). */
        fun fromData(d: Map<String, String>): OrderAlert {
            val items = mutableListOf<OrderItem>()
            try {
                val arr = JSONArray(d["items"] ?: "[]")
                for (i in 0 until arr.length()) {
                    val it = arr.optJSONObject(i) ?: continue
                    items += OrderItem(
                        it.optString("name", it.optString("product_name", "Article")),
                        it.optInt("quantity", 1),
                        it.optDouble("itemPrice", it.optDouble("price", 0.0)),
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
