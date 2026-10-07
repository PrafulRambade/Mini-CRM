@extends('errors.layout')

@section('code', '429')
@section('title', "Too many requests")
@section('message', "You're doing that too often. Please wait a minute and try again.")
@section('icon', 'bi-hourglass-split')
@section('tone', 'warning')
