@extends('errors.layout')

@section('code', '403')
@section('titre', 'Accès refusé')
@section('explication', "Vous n'avez pas les droits nécessaires pour cette action. Selon l'étape du dossier, certaines opérations sont réservées à des rôles précis — un dossier clôturé, en particulier, n'accepte plus aucune modification.")
