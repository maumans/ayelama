@extends('errors.layout')

@section('code', '429')
@section('titre', 'Trop de requêtes')
@section('explication', "Vous avez effectué trop de tentatives en peu de temps. Patientez un instant avant de réessayer.")
