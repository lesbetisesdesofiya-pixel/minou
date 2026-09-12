<?php

// Frais de livraison automatiques.
// - 'default' : forfait appliqué à tous les quartiers (1000 F).
// - 'zones' : exceptions quartier => tarif (ex: 1500 F).
//   La comparaison est insensible à la casse et aux espaces.
//   Le restaurant met à jour cette liste ; le formulaire client
//   affiche automatiquement une liste déroulante quand elle est remplie.

return [

    'default' => (int) env('DELIVERY_FEE_DEFAULT', 1000),

    'zones' => [
        // 'Nom du quartier' => 1500,
        // Envoie la liste des quartiers à 1500 F pour les brancher ici.
    ],

];
