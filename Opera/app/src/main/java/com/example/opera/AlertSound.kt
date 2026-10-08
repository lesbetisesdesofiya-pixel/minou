package com.example.opera

import android.content.Context
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager

/** Alarme cuisine : sonnerie système en boucle + vibration, jusqu'à Accepter/Refuser. */
object AlertSound {
    private var player: MediaPlayer? = null

    @Synchronized
    fun start(ctx: Context) {
        stop()
        try {
            val uri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
                ?: return
            player = MediaPlayer().apply {
                setDataSource(ctx.applicationContext, uri)
                isLooping = true
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_ALARM)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build()
                )
                prepare()
                start()
            }
        } catch (_: Exception) { /* silencieux : la notif visuelle reste */ }
        try {
            val vib: Vibrator? = if (Build.VERSION.SDK_INT >= 31) {
                ctx.getSystemService(VibratorManager::class.java)?.defaultVibrator
            } else {
                @Suppress("DEPRECATION")
                ctx.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
            }
            if (Build.VERSION.SDK_INT >= 26) {
                vib?.vibrate(VibrationEffect.createWaveform(longArrayOf(0, 900, 400, 900), 0))
            } else {
                @Suppress("DEPRECATION")
                vib?.vibrate(longArrayOf(0, 900, 400, 900), 0)
            }
        } catch (_: Exception) {}
    }

    @Synchronized
    fun stop() {
        try { player?.stop() } catch (_: Exception) {}
        try { player?.release() } catch (_: Exception) {}
        player = null
    }
}
